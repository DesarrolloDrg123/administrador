<!doctype html>
<html lang="en">

<head>
    <title>Registro</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-wEmeIV1mKuiNpC+IOBjI7aAzPcEZeedi5yW5f2yOq55WWLwNGmvvx4Um1vskeMj0" crossorigin="anonymous">
    <style>
        html,
        body {
            height: 100%;
        }

        body {
            background: #ffffff;
            color: white;
            display: flex;
            align-items: center;
            padding-top: 40px;
            padding-bottom: 40px;
        }

        .form-signup {
            width: 100%;
            max-width: 400px;
            padding: 15px;
            margin: auto;
            color: #212121;
            border: 4px solid #80bf1f;
            border-radius: 25px;
        }

        .form-signup .form-floating:focus-within {
            z-index: 2;
        }

        .form-signup input {
            margin-bottom: 10px;
        }
    </style>
</head>

<body class="text-center">
    <div class="form-signup bg-light">
        <form action="register_action.php" method="POST">
            <img class="mb-4" src="img/logo-drg.png" alt="drglogo" width="250">
            <h1 class="h3 mb-3 fw-normal">Regístrate</h1>
            <div class="form-floating">
                <input type="text" name="firstname" class="form-control" id="firstname" placeholder="Nombre" required>
                <label for="firstname">Nombre</label>
            </div>
            <div class="form-floating">
                <input type="text" name="lastname" class="form-control" id="lastname" placeholder="Apellido" required>
                <label for="lastname">Apellido</label>
            </div>
            <div class="form-floating">
                <input type="email" name="email" class="form-control" id="email" placeholder="Email" required>
                <label for="email">Email</label>
            </div>
            <div class="form-floating">
                <input type="password" name="password" class="form-control" id="password" placeholder="Contraseña" required>
                <label for="password">Contraseña</label>
            </div>
            <button class="w-100 btn btn-lg btn-dark" type="submit">Registrarse</button>
            <p class="mt-5 mb-3 text-muted">&copy;DRG Services & Solutions.</p>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-p34f1UUtsS3wqzfto5wAAmdvj+osOnFyQFpp4Ua3gs/ZVWx6oOypYoCJhGGScy+8" crossorigin="anonymous"></script>
</body>

</html>
