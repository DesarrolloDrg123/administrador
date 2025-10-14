<?php 
include("src/templates/header.php");
include("config.db.ph");

?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        <?php if (isset($_GET['status'])): ?>
            let status = "<?php echo htmlspecialchars($_GET['status']); ?>";
            let message = "<?php echo isset($_GET['message']) ? htmlspecialchars($_GET['message']) : ''; ?>";
            
            if (status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: 'El mensaje ha sido enviado con éxito.',
                    timer: 3000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'formulario.php'; // Redirige al formulario
                });
            } else if (status === 'error') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo enviar el correo. Error: ' + message,
                    timer: 5000,
                    showConfirmButton: true
                }).then(() => {
                    window.location.href = 'formulario.php'; // Redirige al formulario
                });
            }
        <?php endif; ?>
    </script>

<?php 
include('src/templates/footer.php');