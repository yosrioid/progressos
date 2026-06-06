<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>ProgressOS — {{ ucfirst($period) }} Report</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; background: #fff; }
  .header { background: #0f172a; color: #fff; padding: 20px 28px; margin-bottom: 20px; }
  .header h1 { font-size: 20px; font-weight: 700; letter-spacing: -0.5px; }
  .header .sub { color: #94a3b8; font-size: 11px; margin-top: 3px; }
  .container { padding: 0 28px 28px; }
  .section { margin-bottom: 22px; }
  .section-title { font-size: 13px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
  .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
  .stat-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; text-align: center; }
  .stat-card .value { font-size: 22px; font-weight: 800; color: #6366f1; }
  .stat-card .label { font-size: 10px; color: #64748b; margin-top: 3px; text-transform: uppercase; }
  table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
  th { background: #f1f5f9; color: #475569; font-size: 10px; text-transform: uppercase; letter-spacing: 0.4px; padding: 7px 10px; text-align: left; }
  td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; color: #334155; }
  tr:last-child td { border-bottom: none; }
  .pill { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 9.5px; font-weight: 600; }
  .pill-done { background: #dcfce7; color: #15803d; }
  .pill-blocked { background: #fee2e2; color: #dc2626; }
  .pill-inprog { background: #dbeafe; color: #1d4ed8; }
  .pill-todo { background: #f1f5f9; color: #475569; }
  .bar-wrap { background: #e2e8f0; border-radius: 3px; height: 8px; margin-top: 4px; }
  .bar-fill { background: #6366f1; border-radius: 3px; height: 8px; }
  .footer { margin-top: 28px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 12px; }
</style>
</head>
<body>
<div class="header">
  <h1>ProgressOS — {{ ucfirst($period) }} Report</h1>
  <div class="sub">{{ $report['start'] }} → {{ $report['end'] }} &nbsp;·&nbsp; {{ $user->name }}</div>
</div>

<div class="container">

  {{-- Summary stats --}}
  <div class="grid-4">
    <div class="stat-card">
      <div class="value">{{ count($report['completed_work_logs'] ?? []) }}</div>
      <div class="label">Work Logs Selesai</div>
    </div>
    <div class="stat-card">
      <div class="value">{{ $report['tasks_done'] ?? 0 }}</div>
      <div class="label">Tasks Selesai</div>
    </div>
    <div class="stat-card">
      <div class="value">{{ round(($report['learning_totals']['minutes'] ?? 0) / 60, 1) }}j</div>
      <div class="label">Belajar</div>
    </div>
    <div class="stat-card">
      <div class="value">{{ count($report['open_blockers'] ?? []) }}</div>
      <div class="label">Open Blockers</div>
    </div>
  </div>

  {{-- Work by category --}}
  @if(!empty($report['time_by_category']))
  <div class="section">
    <div class="section-title">Waktu per Kategori</div>
    <table>
      <thead><tr><th>Kategori</th><th>Jam</th><th>Proporsi</th></tr></thead>
      <tbody>
        @php $totalMins = array_sum($report['time_by_category']); @endphp
        @foreach($report['time_by_category'] as $cat => $mins)
        <tr>
          <td>{{ $cat ?: '—' }}</td>
          <td>{{ round($mins/60,1) }}j ({{ $mins }}m)</td>
          <td style="width:200px">
            <div class="bar-wrap">
              <div class="bar-fill" style="width:{{ $totalMins > 0 ? round($mins/$totalMins*100) : 0 }}%"></div>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif

  {{-- Completed work logs --}}
  @if(!empty($report['completed_work_logs']))
  <div class="section">
    <div class="section-title">Work Logs Selesai ({{ count($report['completed_work_logs']) }})</div>
    <table>
      <thead><tr><th>Tanggal</th><th>Judul</th><th>Proyek</th><th>Durasi</th></tr></thead>
      <tbody>
        @foreach(array_slice($report['completed_work_logs'], 0, 30) as $log)
        <tr>
          <td>{{ is_array($log) ? ($log['date'] ?? '') : $log->date }}</td>
          <td>{{ is_array($log) ? ($log['title'] ?? '') : $log->title }}</td>
          <td>{{ is_array($log) ? ($log['project_name'] ?? '—') : ($log->project_name ?? '—') }}</td>
          <td>{{ is_array($log) ? (($log['actual_duration'] ?? 0).'m') : ($log->actual_duration.'m') }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif

  {{-- Open blockers --}}
  @if(!empty($report['open_blockers']))
  <div class="section">
    <div class="section-title">Open Blockers</div>
    <table>
      <thead><tr><th>Judul</th><th>Proyek</th></tr></thead>
      <tbody>
        @foreach($report['open_blockers'] as $log)
        <tr>
          <td>{{ is_array($log) ? ($log['title'] ?? '') : $log->title }}</td>
          <td>{{ is_array($log) ? ($log['project_name'] ?? '—') : ($log->project_name ?? '—') }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif

</div>

<div class="footer">
  Dibuat oleh ProgressOS · {{ now()->format('d M Y H:i') }}
</div>
</body>
</html>
