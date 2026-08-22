<!DOCTYPE html>
<html lang="fr">

<!-- Head and importation des packages -->
<?php include("include/head.php"); ?>

<body>

  <div class="backError">
    <div class="container">
      <section class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
        <div class="row flex-grow">
          <div class="col-lg-9 mx-auto text-white">
            <div class="row align-items-center d-flex flex-row">
              <div class="col-lg-6 text-lg-right pr-lg-4">
                <h5 class="display-1 mb-0 fs-0">403</h5>
              </div>
              <div class="col-lg-6 error-page-divider text-lg-left pl-lg-4">
                <h2><strong>ACCESS REFUSÉ!</strong></h2>
                <h3 class="font-weight-light">Désolé, vous n'avez pas l'autorisation d'accéder à cette page.</h3>
              </div>
            </div>
            <div class="row mt-5">
              <div class="col-12 text-center mt-xl-2">
                <a class="text-white font-weight-medium" href="javascript:history.back()">Retour à la page précédente</a>
              </div>
            </div>
            <div class="row mt-5">
              <div class="col-12 mt-xl-2">
                <p class="text-white font-weight-medium text-center">Copyright &copy; 2024 KABI CONSULTING</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>

  <!-- Footer et importation JavaScript -->
  <?php include("include/footer_2.php"); ?>

</body>

</html>
