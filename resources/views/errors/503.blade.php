<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Em manutenção</title>
    <style>
        :root {
            color-scheme: dark;
        }
        body {
            margin: 0;
            background: #07122e;
            color: #e5e7eb;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen,
                Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif;
            display: grid;
            place-items: center;
            min-height: 100vh;
            text-align: center;
        }
        .card {
            padding: 2rem;
            border-radius: 1rem;
        }
        .logo {
            height: 64px;
            width: auto;
            margin-bottom: 1rem;
        }
        h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.8rem;
        }
        p {
            margin: 0;
            color: #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="card">
        <img class="logo" src="{{ asset('website/assets/logo.png') }}" alt="Zentrum TVDE" />
        <h1>Em manutenção</h1>
        <p>Estamos a melhorar o site. Volte em breve.</p>
    </div>
</body>
</html>
