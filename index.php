<!doctype html>
<html lang="en">

<head>
    <title>Iniciar Sesión</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-wEmeIV1mKuiNpC+IOBjI7aAzPcEZeedi5yW5f2yOq55WWLwNGmvvx4Um1vskeMj0" crossorigin="anonymous">
    <style>
        html,
        body {
            height: 100%;
            margin: 0;
        }

        body {
            background: #ffffff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-top: 40px;
            padding-bottom: 40px;
            background-image: url('img/drg3.png');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            background-repeat: no-repeat;
        }

        .banner-container {
            position: absolute;
            top: 20px;
            width: 100%;
            display: flex;
            justify-content: center;
            z-index: 1;
        }

        .form-signin {
            width: 100%;
            max-width: 350px;
            padding: 15px;
            margin: auto;
            color: #212121;
            border: 4px solid #80bf1f;
            border-radius: 25px;
            background-color: #f8f9fa;
            position: relative;
            z-index: 2;
        }

        .form-signin input[type="email"],
        .form-signin input[type="password"] {
            margin-bottom: 10px;
        }
    </style>
</head>

<body class="text-center">

   

    <div class="form-signin">
        <form action="login.php" method="POST">
            <img class="mb-4" src="img/logo-drg.png" alt="drglogo" width="250">
            <h1 class="h3 mb-3 fw-normal">Inicia Sesion</h1>
            <div class="form-floating">
                <input type="email" name="email" class="form-control" id="email" placeholder="Email" required>
                <label for="email">Email:</label>
            </div>
            <div class="form-floating">
                <input type="password" class="form-control" id="contra" name="contra" placeholder="Contraseña" required autocomplete="FALSE">
                <label for="contra">Contraseña:</label>
            </div>
            <button class="w-100 btn btn-lg btn-dark mb-2" type="submit">Iniciar Sesion</button>
            <p class="mt-5 mb-3 text-muted">&copy;DRG Services & Solutions - 2024.</p>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-p34f1UUtsS3wqzfto5wAAmdvj+osOnFyQFpp4Ua3gs/ZVWx6oOypYoCJhGGScy+8" crossorigin="anonymous"></script>
</body>

</html>
