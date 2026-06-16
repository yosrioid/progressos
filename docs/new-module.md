# Panduan Scaffold Modul CRUD Baru

Ikuti urutan ini saat menambahkan modul baru. Setiap langkah punya contoh konkret dari modul yang sudah ada (`WorkLog` sebagai referensi utama).

---

## 1. Migration

```bash
php artisan make:migration create_things_table
```

Kolom wajib: `id`, `user_id` (FK ke `users`), kolom data modul, `timestamps`.

```php
Schema::create('things', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->string('category')->nullable();
    $table->date('date');
    $table->timestamps();
});
```

---

## 2. Model

```bash
php artisan make:model Thing
```

Wajib ada: `fillable`, `casts`, relasi `user()`, scope `ownedBy()`.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Thing extends Model
{
    protected $fillable = ['user_id', 'title', 'category', 'date'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
```

---

## 3. Policy

Tidak perlu buat policy baru. Daftarkan model ke `OwnedModelPolicy` yang sudah ada di `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php — tambahkan di array yang sudah ada
foreach ([
    // ... model yang sudah ada
    Thing::class,   // ← tambahkan di sini
] as $model) {
    Gate::policy($model, OwnedModelPolicy::class);
}
```

`OwnedModelPolicy` otomatis mengecek `user_id === auth user id` untuk view/update/delete.

---

## 4. Form Request

```bash
php artisan make:request ThingRequest
```

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ThingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth sudah dijaga di middleware
    }

    public function rules(): array
    {
        return [
            'title'    => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'date'     => ['required', 'date'],
        ];
    }
}
```

---

## 5. API Resource

```bash
php artisan make:resource ThingResource
```

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ThingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'category'   => $this->category,
            'date'       => $this->date?->toDateString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

---

## 6. Controller

```bash
php artisan make:controller Api/ThingController
```

Gunakan `ApiResponse` untuk semua return, `ApiQuery` untuk index, `authorize()` untuk show/update/delete.

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ThingRequest;
use App\Http\Resources\ThingResource;
use App\Models\Thing;
use App\Support\ApiQuery;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ThingController extends Controller
{
    public function index(Request $request)
    {
        $query = Thing::ownedBy($request->user());

        return ApiResponse::paginated(
            'things',
            ApiQuery::paginateSorted($query, $request, 'created_at', 20, ['created_at', 'date', 'title']),
            resourceClass: ThingResource::class
        );
    }

    public function show(Request $request, Thing $thing)
    {
        $this->authorize('view', $thing);

        return ApiResponse::item('thing', new ThingResource($thing));
    }

    public function store(ThingRequest $request)
    {
        $thing = $request->user()->things()->create($request->validated());

        return ApiResponse::item('thing', new ThingResource($thing), 201, 'Thing created.');
    }

    public function update(ThingRequest $request, Thing $thing)
    {
        $this->authorize('update', $thing);
        $thing->update($request->validated());

        return ApiResponse::item('thing', new ThingResource($thing->fresh()), 200, 'Thing updated.');
    }

    public function destroy(Thing $thing)
    {
        $this->authorize('delete', $thing);
        $thing->delete();

        return response()->noContent();
    }
}
```

---

## 7. Route

Daftarkan di `routes/api/v1.php`. GET di grup `ability:read`, write di grup `ability:write`.

```php
// Di dalam grup middleware(['ability:read', 'throttle:api-read'])
Route::get('things', [ThingController::class, 'index']);
Route::get('things/{thing}', [ThingController::class, 'show']);

// Di dalam grup middleware(['ability:write', 'throttle:api-write'])
Route::post('things', [ThingController::class, 'store']);
Route::put('things/{thing}', [ThingController::class, 'update']);
Route::delete('things/{thing}', [ThingController::class, 'destroy']);
```

---

## 8. Frontend — Konfigurasi Record (Generic Views)

Jika modul bisa pakai generic `Records.vue` / `RecordForm.vue` / `RecordDetail.vue`, tambahkan config di `resources/js/vue/records.ts`:

```typescript
// Tambahkan ke array configs
{
  type: 'things',
  singular: 'thing',
  apiBase: '/api/v1/things',
  label: 'Things',
  titleKey: 'title',
  dateKey: 'date',
  fields: [
    { key: 'title', label: 'Title', required: true },
    { key: 'category', label: 'Category', type: 'select', options: ['work', 'personal'] },
    { key: 'date', label: 'Date', type: 'date', required: true },
  ],
  sortOptions: [
    { value: 'date', label: 'Date' },
    { value: 'created_at', label: 'Created' },
  ],
  emptyState: ['No things yet', 'Start by creating your first thing.'],
},
```

Jika modul butuh tampilan khusus, buat view baru di `resources/js/vue/views/ThingList.vue` dan ikuti pola `Goals.vue` atau `Habits.vue`.

---

## 9. Frontend — Router

Tambahkan routes di `resources/js/vue/router.ts`:

```typescript
// Generic routes (pakai Records/RecordForm/RecordDetail)
{ path: '/things', component: () => import('./views/Records.vue'), props: { type: 'things' } },
{ path: '/things/create', component: () => import('./views/RecordForm.vue'), props: { type: 'things' } },
{ path: '/things/:id', component: () => import('./views/RecordDetail.vue'), props: (r) => ({ type: 'things', id: r.params.id }) },
{ path: '/things/:id/edit', component: () => import('./views/RecordForm.vue'), props: (r) => ({ type: 'things', id: r.params.id }) },
```

---

## 10. Frontend — Navigasi Sidebar

Tambahkan di `navGroups` di `App.vue`:

```typescript
// Tambahkan ke group yang sesuai
{ label: 'Things', href: '/things', icon: 'check' },
```

Icon yang tersedia: `dashboard`, `folder`, `chart`, `calendar`, `briefcase`, `check`, `book`, `target`, `games`, `settings`, `user`, `docs`.

---

## 11. Test

Wajib tambahkan test di `tests/Feature/`. Minimal cover:

```php
// tests/Feature/ThingTest.php
it('lists things owned by user', ...);
it('cannot list things of another user', ...);
it('creates a thing', ...);
it('validates required fields on create', ...);
it('updates a thing', ...);
it('cannot update thing of another user', ...);
it('deletes a thing', ...);
it('cannot delete thing of another user', ...);
```

Jalankan:

```bash
php artisan test --filter=ThingTest
```

---

## Checklist Singkat

- [ ] Migration dibuat dan dijalankan
- [ ] Model punya `fillable`, `casts`, `user()`, `scopeOwnedBy()`
- [ ] Model didaftarkan ke `OwnedModelPolicy` di `AppServiceProvider`
- [ ] Form Request dengan `rules()` yang tepat
- [ ] API Resource dengan field yang diperlukan
- [ ] Controller: semua return pakai `ApiResponse`, index pakai `ApiQuery`, ada `authorize()` di show/update/delete
- [ ] Route di grup yang benar (`read` vs `write`)
- [ ] Config di `records.ts` atau view khusus dibuat
- [ ] Route di `router.ts`
- [ ] Entry di `navGroups` di `App.vue`
- [ ] Test feature mencakup CRUD + unauthorized cases
- [ ] `npm run build` tidak ada error
