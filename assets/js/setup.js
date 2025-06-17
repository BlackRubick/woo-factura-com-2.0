// JavaScript para el setup wizard
jQuery(document).ready(function($) {
    // Validación de formularios del wizard
    $('form').on('submit', function(e) {
        var form = $(this);
        var isValid = true;
        
        // Validar campos requeridos
        form.find('[required]').each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).css('border-color', 'red');
            } else {
                $(this).css('border-color', '');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Por favor completa todos los campos requeridos');
        }
    });
});