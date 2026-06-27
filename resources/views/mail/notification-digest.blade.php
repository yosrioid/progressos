<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ProgressOS Notifications</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; margin: 0; padding: 0; color: #1e293b; }
  .wrapper { max-width: 560px; margin: 32px auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
  .header { background: #0f766e; padding: 24px 28px; }
  .header h1 { margin: 0; color: #ffffff; font-size: 18px; font-weight: 800; letter-spacing: -0.01em; }
  .header p { margin: 4px 0 0; color: #99f6e4; font-size: 13px; }
  .body { padding: 24px 28px; }
  .greeting { font-size: 14px; color: #475569; margin: 0 0 20px; }
  .notif { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 10px; }
  .notif-title { font-size: 14px; font-weight: 700; color: #0f172a; margin: 0 0 4px; }
  .notif-body { font-size: 13px; color: #64748b; margin: 0; }
  .notif-type { display: inline-block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #0f766e; background: #f0fdf4; border-radius: 4px; padding: 1px 6px; margin-bottom: 6px; }
  .notif-link { display: inline-block; margin-top: 8px; font-size: 12px; font-weight: 600; color: #0f766e; text-decoration: none; }
  .footer { padding: 16px 28px; border-top: 1px solid #f1f5f9; font-size: 12px; color: #94a3b8; }
  .footer a { color: #0f766e; text-decoration: none; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>ProgressOS</h1>
    <p>Daily notification digest</p>
  </div>
  <div class="body">
    <p class="greeting">Hi {{ $recipient->name }}, here are your notifications for today:</p>

    @foreach ($notifications as $notif)
    <div class="notif">
      <div class="notif-type">{{ str_replace('_', ' ', $notif->type) }}</div>
      <p class="notif-title">{{ $notif->title }}</p>
      <p class="notif-body">{{ $notif->body }}</p>
      @if ($notif->action_url)
      <a class="notif-link" href="{{ config('app.url') }}{{ $notif->action_url }}">View →</a>
      @endif
    </div>
    @endforeach
  </div>
  <div class="footer">
    <a href="{{ config('app.url') }}">Open ProgressOS</a> · You're receiving this because you have unread notifications.
  </div>
</div>
</body>
</html>
