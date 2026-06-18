<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('No workspace') }} · StaffPick</title>
    {{-- Self-contained styles so this dead-end renders without any build dependency. --}}
    <style>
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1f2937; }
        .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { max-width: 460px; width: 100%; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 36px 32px; box-shadow: 0 1px 3px rgba(0,0,0,.06); text-align: center; }
        .brand { font-size: 26px; font-weight: 800; color: #1d4ed8; letter-spacing: -.5px; }
        h1 { font-size: 19px; margin: 18px 0 8px; color: #111827; }
        p { font-size: 14px; line-height: 1.6; color: #4b5563; margin: 0; }
        a.email { color: #1d4ed8; text-decoration: none; font-weight: 600; }
        .actions { margin-top: 26px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-block; border-radius: 9px; padding: 9px 16px; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid transparent; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #fff; color: #374151; border-color: #d1d5db; }
        form { margin: 0; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="brand">StaffPick</div>
            <h1>{{ __('You\'re signed in') }}</h1>
            <p>
                {{ __('You\'ve been signed in, but you don\'t belong to any workspace yet.') }}
                {{ __('Contact your administrator or reach out to') }}
                <a class="email" href="mailto:support@staffpick.dev">support@staffpick.dev</a>
                {{ __('to be added.') }}
            </p>
            <div class="actions">
                <a class="btn btn-primary" href="mailto:support@staffpick.dev">{{ __('Email support') }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">{{ __('Sign out') }}</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
