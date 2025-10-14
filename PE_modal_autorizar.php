<?php
session_start();
$folio = $_GET['folio'] ?? null;
$autorizador = $_GET['autorizador'] ?? null;
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: index.php");
    exit();
}
if ($folio) {
}

?>
<div class="modal-header">
  <h5 class="modal-title" id="exampleModalLabel">Autorizar Pedido Especial - <?php echo htmlspecialchars($folio); ?></h5>
  <button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar">
    <span aria-hidden="true">&times;</span>
  </button>
</div>
<div class="modal-body">
  <form>
    <div class="form-group">
      <label for="inputText">Capturar Orden de Compra</label>
      <input type="text" class="form-control" name="orden_compra" id="txtAutorizar" placeholder="Orden de Compra" required>
      <input type="hidden" name="operacion" value="Autorizar">
      <input type="hidden" name="folio" value="<?php echo htmlspecialchars($folio); ?>">
      <input type="hidden" name="autorizador" value="<?php echo htmlspecialchars($autorizador); ?>">
    </div>
  </form>
</div>
<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
  <button type="button" id="guardarBtn" class="btn btn-primary">Guardar</button>
</div>
<div id="mensaje" style="display:none; margin-top: 10px;"></div> <!-- Mensaje de éxito/error -->

<script>
$(document).ready(function() {
    $('#guardarBtn').on('click', function() {
        // Obtener los datos del formulario
        let folio = $("input[name='folio']").val();
        let ordenCompra = $("input[name='orden_compra']").val();
        let autorizador = $("input[name='autorizador']").val();
        let operacion = $("input[name='operacion']").val();

        // Validar que el campo no esté vacío
        if (!ordenCompra) {
            $('#mensaje').text('Por favor, ingrese una orden de compra.').show();
            return;
        }

        // Enviar datos mediante AJAX
        $.ajax({
            url: 'PE_controller/auto_dev_rec.php',
            type: 'POST',
            data: { folio: folio, orden_compra: ordenCompra, operacion: operacion, autorizador: autorizador },
            success: function(response) {
                $('#mensaje').text(response).show(); // Mostrar mensaje de éxito
                setTimeout(function() {
                    $('#exampleModal').modal('hide'); // Cerrar el modal
                    location.reload(); // Recargar la página
                }, 3000); // Esperar 5 segundos
            },
            error: function() {
                $('#mensaje').text('Ocurrió un error al guardar.').show(); // Mensaje de error
            }
        });
    });
});
</script>
