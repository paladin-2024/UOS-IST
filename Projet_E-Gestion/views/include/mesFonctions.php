<script>
  document.addEventListener('DOMContentLoaded', function() {

    // Initialisation des listes déroulantes au chargement de la page
    document.querySelectorAll('.dselect').forEach(function(select) {
      dselect(select, {
        search: true
      });
    });

    // Initialisation de Select2 sur tous les éléments avec la classe 'select-class'
    document.querySelectorAll('.select-class').forEach(function(element) {
            $(element).select2();
        });

    // Initialiser Ladda sur tous les boutons avec la classe ladda-button
    Ladda.bind('.ladda-button', {
      callback: function(instance) {
        var progress = 0;
        var interval = setInterval(function() {
          progress = Math.min(progress + Math.random() * 0.1, 1);
          instance.setProgress(progress);

          if (progress === 1) {
            clearInterval(interval);
          }
        }, 200);
      }
    });

    // Ajouter l'événement submit à tous les formulaires avec la classe ladda-form
    document.querySelectorAll('.ladda-form').forEach(function(form) {
      form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
          form.classList.add('has-validated');
          return;
        }

        var laddaButton = form.querySelector('.ladda-button');
        var l = Ladda.create(laddaButton);
        l.start();

        // Empêcher la soumission multiple
        setTimeout(function() {
          if (form.checkValidity()) {
            l.stop();
            form.submit(); // Envoyer le formulaire après l'animation
          }
        }, 1000);

        event.preventDefault(); // Assurer qu'on ne soumet pas plusieurs fois
      });
    });

  });
</script>