<?php
// This was an empty stub (header+footer, no content) linked from the
// sidebar as "Tableau de bord" -- the real general dashboard already
// lives at views/index.php, so redirect there instead of duplicating it.
header('Location: ' . BASE_PATH . 'index');
exit;
