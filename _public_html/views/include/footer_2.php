  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  
  <script>
    window.addEventListener('load', function() {
        const preloader = document.getElementById('preloader');
        
        // Masquer le preloader après le chargement de la page
        setTimeout(() => {
            preloader.style.opacity = '0';
            preloader.style.visibility = 'hidden';
            setTimeout(() => preloader.style.display = 'none', 500);
        }, 500);

        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener("submit", function() {
                // Check if all required fields are complete
                if (!areFieldsComplete(form)) {
                    event.preventDefault(); // Prevent form submission
                    Swal.fire({
                            icon: 'warning',
                            title: 'Champs incomplets',
                            text: 'Veuillez remplir tous les champs requis.',
                            confirmButtonText: 'OK'
                        });
                        return;
                }

                // Afficher le preloader
                preloader.style.display = "flex";
                preloader.style.opacity = "1";

                
            });
        });

    });
</script>






  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/quill/quill.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>

  <!-- Template Main JS File -->
  <script src="assets/js/main.js"></script>
  <script src="assets/js/sweetalert.min.js"></script>
  
  <script>
    // Affichage mot de passe
    let checkbox = document.querySelector("#rememberMe");

    function changer() {
      if (checkbox.checked) {
        document.getElementById("yourPassword").setAttribute("type", "text");
      } else {
        document.getElementById("yourPassword").setAttribute("type", "password");
      }
    }
  </script>

</body>

</html>
