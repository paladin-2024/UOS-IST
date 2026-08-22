<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 - Page non trouvée</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .backError {
      background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
      min-height: 100vh;
    }
    .error-page-divider {
      border-left: 3px solid rgba(255,255,255,0.3);
    }
    @media (max-width: 991px) {
      .error-page-divider {
        border-left: none;
        border-top: 3px solid rgba(255,255,255,0.3);
        padding-top: 1rem;
      }
    }
    .display-1 {
      font-size: 8rem;
      font-weight: 700;
      line-height: 1;
    }
    @media (max-width: 768px) {
      .display-1 {
        font-size: 5rem;
      }
    }
  </style>
</head>
<body>
  <div class="backError">
    <div class="container">
      <section class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
        <div class="row flex-grow">
          <div class="col-lg-9 mx-auto text-white">
            <div class="row align-items-center d-flex flex-row">
              <div class="col-lg-6 text-lg-right pr-lg-4">
                <h5 class="display-1 mb-0 fs-0">404</h5>
              </div>
              <div class="col-lg-6 error-page-divider text-lg-left pl-lg-4">
                <h2><strong>SORRY!</strong></h2>
                <h3 class="font-weight-light">Oops, Nous ne parvenons pas à trouver la page demandée</h3>
              </div>
            </div>
            <div class="row mt-5">
              <div class="col-12 text-center mt-xl-2">
                <a class="text-white font-weight-medium text-decoration-none" href="javascript:history.back()">Retour à la page précédente</a>
              </div>
            </div>
            <div class="row mt-5">
              <div class="col-12 mt-xl-2">
                <p class="text-white font-weight-medium text-center mb-0">Copyright &copy; 2024 E-GESTION</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</body>
</html>