<?php
include("./src/templates/header.php");

require("./config/db.php");



?>

<br>


<container class="d-flex justify-content-center">

    <form method="POST" action="#">

        <h1 class="text-center mb-3">Transferencia Electronica</h1>


        <div class="row">
            <div class="col">
                <img src="img/logo-drg.png" alt="col" class="" class="align-center">
            </div>
            <div class="col">
                <h3>Folio: <span style="color:red;">00001</span></h3>
            </div>
        </div>
        <br>

        <div class="row mb-1">
            <div class="col">
                <h5>
                    <label for="sucursal">Sucursal</label>
                    <select name="sucursales" id="sucursal">
                        <?php
                        try {
                            $stmt = $conn->prepare("SELECT id, sucursal FROM sucursales");
                            $stmt->execute();

                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['sucursal']) . '</option>';
                            }
                        } catch (PDOException $e) {
                            echo "Error: " . $e->getMessage();
                        }
                        ?>
                    </select>
                </h5>

            </div>
        </div>


        <div class="row mb-1">
            <div class="col">
                <label for="beneficiario">Beneficiario</label>
                <select class="form-label" name="beneficiario" id="beneficiario">
                    <?php
                    try {
                        $stmt = $conn->prepare("SELECT id, beneficiario FROM beneficiarios");
                        $stmt->execute();

                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['beneficiario']) . '</option>';
                        }
                    } catch (PDOException $e) {
                        echo "Error: " . $e->getMessage();
                    }
                    ?>
                </select>


            </div>

            <div class="col">
                <label class="form-label">Fecha de Solicitud</label>
                <input type="date" name="date" class="form-control" placeholder="" aria-label="" required>
            </div>
        </div>

        <div class="row mb-1">
            <div class="col">
                <label class="form-label">No. de Cuenta</label>
                <input id="noCuenta" type="text" name="noCuenta" class="form-control" placeholder="No. de Cuenta" aria-label="No. de Cuenta" required oninput="this.value = this.value.toUpperCase()">
            </div>
            <div class="col">
                <label class="form-label">Fecha de Vencimiento</label>
                <input type="date" name="endDAte" class="form-control" placeholder="Fecha De Vencimiento" aria-label="" required>
            </div>
        </div>




        <div class="row mb-1">

            <div class="col">
                <label class="form-label">Importe en pesos: </label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" step="0.01" name="importe" id="importe" class="form-control" placeholder="0.00" aria-label="Importe" required oninput="convertirALetra(this.value)">
                </div>
            </div>

            <div class="col">
                <label class="form-label">Importe Con Letra</label>
                <input type="text" name="importe-letra" id="importe-letra" class="form-control" placeholder="Importe Con Letra" aria-label="Importe Con Letra" required readonly>
            </div>
        </div>


        <div class="row mb-1">

            <div class="col">
                <br>

                <label for="departamento" class="form-label">Departamento</label>
                <select class="form-label" name="departamento" id="departamento" class="form-control">
                    <?php
                    try {
                        $stmt = $conn->prepare("SELECT id, departamento FROM departamentos");
                        $stmt->execute();

                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['departamento']) . '</option>';
                        }
                    } catch (PDOException $e) {
                        echo "Error: " . $e->getMessage();
                    }
                    ?>
                </select>
            </div>

            <div class="col">
            <br>
            <label for="categoria" class="form-label">Categoria</label>
                <select class="form-label" name="categoria" id="categoria" class="form-control">
                    <?php
                    try {
                        $stmt = $conn->prepare("SELECT id, categoria FROM categorias");
                        $stmt->execute();

                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['categoria']) . '</option>';
                        }
                    } catch (PDOException $e) {
                        echo "Error: " . $e->getMessage();
                    }
                    ?>
                </select>            
            </div>
        </div>


        <div class="row mb-1">
            <div class="col">
                <label class="form-label">Descripcion</label>
                <textarea type="text" class="form-control" name="descripcion" id="descripcion" placeholder="Descripcion"></textarea>
            </div>
        </div>

        <div class="row mb-1">
            <div class="col">
                <label for="" class="form-label">Observaciones especiales</label>
                <textarea type="text" class="form-control" name="observaciones" id="observaciones" placeholder="Observaciones"></textarea>
            </div>

            <div class="col">
                <br>
                <label for="usuarios" class="form-label">Usuario que Autoriza</label>

                <select name="autorizacion" id="autorizar">
                    <?php
                    try {
                        $stmt = $conn->prepare("SELECT id, nombre FROM usuarios");
                        $stmt->execute();

                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['nombre']) . '</option>';
                        }
                    } catch (PDOException $e) {
                        echo "Error: " . $e->getMessage();
                    }
                    ?>
                </select>

            </div>
        </div>

        <button type="submit" class="btn btn-sucess mt-2">Enviar</button>
    </form>
</container>


<?php include("./src/templates/footer.php"); ?>