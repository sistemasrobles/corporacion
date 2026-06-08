<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Iniciar sesión | {{ config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/src/css/gentelella.css">
</head>
<body>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-brand">
      <div class="brand-icon">O</div>
      <div class="brand-name">{{ config('app.name') }}</div>
    </div>

    <div class="auth-title">Bienvenido</div>
    <div class="auth-subtitle">Ingresa tus credenciales para continuar.</div>

    @if ($errors->any())
      <div class="banner banner-danger" style="margin-bottom:16px">
        <svg class="banner-icon" width="18" height="18" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6"/><path d="M5 5l6 6M11 5l-6 6"/></svg>
        <div class="banner-body">{{ $errors->first() }}</div>
      </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
      @csrf

      <div class="form-group">
        <label class="form-label" for="email">Correo electrónico</label>
        <div class="input-group">
          <svg class="input-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="12" height="10" rx="1.5"/><path d="M2 5l6 4 6-4"/></svg>
          <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="tu@empresa.com" autocomplete="email" required autofocus>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Contraseña</label>
        <div class="input-group">
          <svg class="input-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="7" width="10" height="7" rx="1.5"/><path d="M5 7V5a3 3 0 016 0v2"/></svg>
          <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" autocomplete="current-password" required>
        </div>
      </div>

      <div class="auth-actions">
        <label class="form-check">
          <input type="checkbox" name="remember" value="1"> Recordarme
        </label>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;height:38px">
        Ingresar al sistema
      </button>
    </form>

    <div class="auth-footer">
      © {{ date('Y') }} {{ config('app.name') }}
    </div>
  </div>
</div>

</body>
</html>