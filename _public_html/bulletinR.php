<?php
include "parties/head_jury.php";
include 'PHPExcel.php';
include 'PHPExcel/Writer/Excel2007.php';

error_reporting(E_ALL);
ini_set("display_errors", 0);

$b = new Promotion();
$c = new Fichedecote();
$conf = new Configuration();
$et = new Etudiant();
$getConf = $conf->getConfig()->fetch_object();



$c = new Fichedecote();
//Initialisation
//$c->initialiser($_SESSION['idECUE'], $_SESSION['semestre'], $session, $_SESSION['annee']);
//
?>
<div class="main-panel">
    <div class="content-wrapper">
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            Espace Jury : <?php if($_SESSION['ratra'] == 0) echo $_SESSION['des_semestre']; else  echo $_SESSION['des_semestre']." ( Rattrapage S1+S2 )"; ?>
                            <a href="index.php?view=menuDelib" class="btn btn-light float-right">
                                <i class="fa fa-hand-o-left"></i> Retour
                            </a>
                        </h4>
                        <div class="table-responsive">
                            <?php
                            $etu = $et->getEtudiantRat($_SESSION['semestre'], $annee);
                            $workbook = new PHPExcel;
                            
							
                            while ($ligne = $etu->fetch_object()) {
								
								$etudiant1=$ligne->idEtudiant;
								$etudiant2=$ligne->idEtudiant;
							
								//Fin Recupération
								
								
                                //Calcul de la moyenne annuelle et credit Annuel
								//Calculs pour la fin de l'annéee
							
								$somMoyenne=0;
								$somMaximum=0;
								$somPoints=0;
								$somCredit=0;
								
								//Fin calculs
								
								//Variables de catégories
								$somCatAsem=0;$iCatA=0;
								$somCatBsem=0;$iCatB=0;
								
								
								$somCatAsem2=0;$iCatA2=0;
								$somCatBsem2=0;$iCatB2=0;
								
								
                                
                                $idEt = $ligne->idEtudiant;
								
								
                                $infosEt = $et->getEtudiantById($idEt)->fetch_object();
                                $nomEtudiant = $infosEt->noms;
								$matEtudiant = $infosEt->matricule;
								$lieuNaiss = $infosEt->lieuNaissance;
								$dateNaiss = $infosEt->dateNaissance;
                                $n = $infosEt->noms;
                                $trois = $n[0] . '' . $n[1] . '' . $n[2] . '' . $n[3] . '' . $n[4];

                                $name = 'Bulletin_' . $idEt . '_' . $trois;
                                $z = $workbook->createSheet();
                                
                                
                                // Protection de la feuille de calcul
                                $z->getProtection()->setSheet(true);
                                $z->getProtection()->setPassword($config->ville);
                                $z->getProtection()->setSort(true);
                                $z->getProtection()->setInsertRows(true);
                                $z->getProtection()->setFormatCells(true);

								// Ajuster la largeur à une page
								$z->getPageSetup()->setFitToWidth(1);
								$z->getPageSetup()->setFitToHeight(0); // Optionnel, ajuster pour plusieurs pages en hauteur
								
								// Définir des marges étroites
								$z->getPageMargins()->setTop(0.25); // 0.25 pouces
								$z->getPageMargins()->setRight(0.25); // 0.25 pouces
								$z->getPageMargins()->setLeft(0.25); // 0.25 pouces
								$z->getPageMargins()->setBottom(0.25); // 0.25 pouces
								$z->getPageMargins()->setHeader(0.1); // 0.1 pouces pour l'en-tête
								$z->getPageMargins()->setFooter(0.1); // 0.1 pouces pour le pied de page
                                
                                
                                $z->setTitle($name);
                                $z->getHeaderFooter()->setOddFooter('Légende : A=Excellent, B=Très Bien, C=Bien, D=Assez Bien, E=Passable, F=Insuffisant, G=Insatisfait---VP=Validé partiellement, VT=Validé Totalement, NV=Non Validé');

                                $s = $getConf->nomination . '';
								
								//Style par défaut de la page

								$z->getDefaultStyle()->applyFromArray(array(
								'font'=>array(
								'name' => 'Verdana',
								'size'=>10,
								'bold' => false)
								)
								);
								
								
                                //Entete du bulletin
                                include 'controller/enteteBulletinS2okR.php';
                                //Fin
								
                                $prom=$b->getProBySem($_SESSION['semestre'])->fetch_row();
								
								//Recupération des semestres de cette promotion
								
								$rqSem=$b->getSemByPromotion($prom[0]);
								$semestres=array();
								$r=0;
								while($rSem=$rqSem->fetch_row()){
									$semestres[$r]=$rSem[0];
									$r++;
								}
								
								$semestre1=$semestres[0];
								$semestre2=$semestres[1];
								$session1="troisieme";
								$session2="troisieme";
								
								//Fin recupération semestre
								
								
								//Première partie du Relevé Semestre 1
								
                                $rs1 = $b->getUEBySem($semestre1);
                                $ii = 1;
                                $m = 0;
                                $echecUE = 0;
                                $i = 9;
                                $compt = 1;
                                $moyenneCumul = 0;
                                $somMax = 0;
                                $echecECUE = 0;
                                $creditInvalide=0;$decision="";
								$codeS=0;$isVide=false;$isVide2=false;
                                while ($l = $rs1->fetch_object()) {
									
									
                                    $crUE = ($l->CMI + $l->TD + $l->TP) / 15;
									
                                    include 'controller/partie2Releve.php';
									
                                    $rs2 = $c->cotes2($etudiant1, $session1, $l->idUE);
                                    $mention = "";
                                    $jury = "";
                                    $pointCumul = 0;
                                    $maxCumul = 0;
									$testUnite=false;
                                    if($nombreECUE>1){
										$i++;
									}
                                    while ($li = $rs2->fetch_row()) {
                                        $crECUE = ($li[3] + $li[4] + $li[5]) / 15;
                                        $point = $c->plafond(round(($li[1] + $li[2]) / 2, 1));

                                        $pointCumul = $pointCumul + ($point * $crECUE);
                                        $maxCumul = $maxCumul + ($crECUE * 20);
                                        $m = $m + $point;
                                       if ($point < 10 || ($li[1]==NULL) || ($li[2]==NULL)) {
											if($nombreECUE>1){
												$jury = "Non validé";
											}else{
												$jury = "Non validée";
											}
                                            
                                        } else{
											if($nombreECUE>1){
												$jury = "Validé";
											}else{
												$jury = "Validée";
											}
										}

                                        $mention = $c->mentions($point);

                                        include 'controller/partie3Releve.php';
                                        $i++;
                                    }
                                    if ($maxCumul != 0)
                                        $pointUE = $c->plafond(round(($pointCumul / $maxCumul) * 20, 1));
                                    else
                                        $pointUE = 0;
									
									//Somme par catégorie
									
									if($l->categorie=="A"){
										$somCatAsem=$somCatAsem+$pointUE;
										$iCatA=$iCatA+1;
									}else{
										$somCatBsem=$somCatBsem+$pointUE;
										$iCatB=$iCatB+1;
									}
									
									
									
                                    $moyenneCumul = $moyenneCumul + ($pointUE * $crUE);
                                    $somMax = $somMax + ($crUE * 20);
                                    $juryUE = "";
                                    $mentionUE = "";
                                    if ($pointUE < 10 || $testUnite==true) {
                                        $juryUE = "Non validé";
                                        $echecUE = $echecUE + 1;
                                        $echecECUE = $echecECUE + $crUE;
                                        $creditInvalide = $creditInvalide+$crUE;
                                        
                                        //Historique de validation
                                       $c->historiquecr($crUE, $ligne->idEtudiant, $ligne->matricule, $l->idUE,$l->codeUE, $_SESSION['semestre'], $annee);
                                        //Fin
                                        
                                    } else{
                                        $juryUE = "Validée";
                                        //Historique de validation
                                        $c->historiquecrb($crUE, $ligne->idEtudiant, $ligne->matricule, $l->idUE,$l->codeUE, $_SESSION['semestre'], $annee);
                                        //Fin
                                    }

                                    $mentionUE = $c->mentions($pointUE);
                                    //$i = $i + 1;
                                    //Sous total
                                    //$i++;
                                    include 'controller/partie4Releve.php';
                                    //$i++;
                                    $ii++;
                                }
                                $rsMoyenne = round(($moyenneCumul / $somMax) * 20, 2);
                                $Mgen = $c->mentions($rsMoyenne);
                                $pourc = round(($rsMoyenne / 20) * 100, 2);
                                
                                $crSem=$somMax/20;
                                
                                if($creditInvalide==$crSem){
                                  $decision="NV";
                                }else if($creditInvalide<$crSem && $creditInvalide!=0){
                                  $decision="VP";
                                }else if($creditInvalide==0){ 
                                  $decision="VT";
                                }
                                
								if($isVide==true) $decision="VP";
                                //Les totaux
                                include 'controller/partie5Releve.php';
								
								//Ajout des Moyennes par Catégorie
								
								
								$i = $i + 1;

								$moyenneCA=round($somCatAsem/$iCatA,2);
								$moyenneCB=round($somCatBsem/$iCatB,2);

								//Resultats 2

								$z->setCellValueByColumnAndRow(0, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 0);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));



								$z->setCellValueByColumnAndRow(1, $i, 'Moyenne Catégorie(A)');
								$style = $z->getStyleByColumnAndRow($i, 1);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(1, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
								$styleA1 = $z->getStyleByColumnAndRow(1, $i);
								$styleFont = $styleA1->getFont();
								$styleFont->setBold(true);
								$styleFont->setSize(10);

								$z->setCellValueByColumnAndRow(2, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 2);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(2, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
											
											
								if($isVide==true)
								$z->setCellValueByColumnAndRow(3, $i, "");
								else
								$z->setCellValueByColumnAndRow(3, $i, $moyenneCA);

								$style = $z->getStyleByColumnAndRow($i, 3);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(3, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));

								$styleA1 = $z->getStyleByColumnAndRow(3, $i);
								$styleFont = $styleA1->getFont();
								$styleFont->setBold(true);
								$styleFont->setSize(10);


								$z->setCellValueByColumnAndRow(4, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 4);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));
								$bordure = $z->getStyleByColumnAndRow(4, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));


								$z->setCellValueByColumnAndRow(5, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 5);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(5, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));

								$z->setCellValueByColumnAndRow(6, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 6);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(6, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
											

								$z->setCellValueByColumnAndRow(7, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 7);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(7, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
								
								
								
								
								
								
								
								$i = $i + 1;

								//Resultats 2

								$z->setCellValueByColumnAndRow(0, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 0);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));



								$z->setCellValueByColumnAndRow(1, $i, 'Moyenne Catégorie(B)');
								$style = $z->getStyleByColumnAndRow($i, 1);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(1, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
								$styleA1 = $z->getStyleByColumnAndRow(1, $i);
								$styleFont = $styleA1->getFont();
								$styleFont->setBold(true);
								$styleFont->setSize(10);

								$z->setCellValueByColumnAndRow(2, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 2);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(2, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
											
											
								if($isVide==true)
								$z->setCellValueByColumnAndRow(3, $i, "");
							    else
								$z->setCellValueByColumnAndRow(3, $i, $moyenneCB);
								$style = $z->getStyleByColumnAndRow($i, 3);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(3, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));

								$styleA1 = $z->getStyleByColumnAndRow(3, $i);
								$styleFont = $styleA1->getFont();
								$styleFont->setBold(true);
								$styleFont->setSize(10);


								$z->setCellValueByColumnAndRow(4, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 4);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));
								$bordure = $z->getStyleByColumnAndRow(4, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));


								$z->setCellValueByColumnAndRow(5, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 5);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(5, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));

								$z->setCellValueByColumnAndRow(6, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 6);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(6, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
											

								$z->setCellValueByColumnAndRow(7, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 7);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(7, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
								
								
								
								
								
								
								$i=$i+1;
								$plage="A".$i.":H".$i;
								$z->mergeCells($plage);
								
								$z->setCellValueByColumnAndRow(0, $i, 'SEMESTRE 2');
								$style = $z->getStyleByColumnAndRow(0, $i);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyle($plage);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
								$z->getStyle($plage)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
								
								
								//Actualisation des sommes
								
								$somMoyenne=$somMoyenne+$rsMoyenne;
								$somMaximum=$somMaximum+$somMax;
								$somPoints=$somPoints+$moyenneCumul;
								$somCredit=$somCredit+$creditCapitalise;
								
								
								
								
								//Deuxieme partie du Relevé Semestre 2
								
                                $rs1 = $b->getUEBySem($semestre2);
                                $ii = 1;
                                $m = 0;
                                $echecUE = 0;
                                $i = $i+1;
                                $compt = 1;
                                $moyenneCumul = 0;
                                $somMax = 0;
                                $echecECUE = 0;
                                $creditInvalide=0;$decision="";
								$codeS=0;$isVide=false;
                                while ($l = $rs1->fetch_object()) {
                                    $crUE = ($l->CMI + $l->TD + $l->TP) / 15;
									
                                    include 'controller/partie2Releve.php';
									
                                    $rs2 = $c->cotes2($etudiant2, $session2, $l->idUE);
                                    $mention = "";
                                    $jury = "";
                                    $pointCumul = 0;
                                    $maxCumul = 0;
									$testUnite=false;
                                    if($nombreECUE>1){
										$i++;
									}
                                    while ($li = $rs2->fetch_row()) {
                                        $crECUE = ($li[3] + $li[4] + $li[5]) / 15;
                                        $point = $c->plafond(round(($li[1] + $li[2]) / 2, 1));

                                        $pointCumul = $pointCumul + ($point * $crECUE);
                                        $maxCumul = $maxCumul + ($crECUE * 20);
                                        $m = $m + $point;
                                        if ($point < 10 || ($li[1]==NULL) || ($li[2]==NULL)) {
											if($nombreECUE>1){
												$jury = "Non validé";
											}else{
												$jury = "Non validée";
											}
                                            
                                        } else{
											if($nombreECUE>1){
												$jury = "Validé";
											}else{
												$jury = "Validée";
											}
										}

                                        $mention = $c->mentions($point);

                                        include 'controller/partie3Releve.php';
                                        $i++;
                                    }
                                    if ($maxCumul != 0)
                                        $pointUE = $c->plafond(round(($pointCumul / $maxCumul) * 20, 1));
                                    else
                                        $pointUE = 0;
									
									
									
									//Somme par catégorie
									
									if($l->categorie=="A"){
										$somCatAsem2=$somCatAsem2+$pointUE;
										$iCatA2=$iCatA2+1;
									}else{
										$somCatBsem2=$somCatBsem2+$pointUE;
										$iCatB2=$iCatB2+1;
									}
									
									
									
                                    $moyenneCumul = $moyenneCumul + ($pointUE * $crUE);
                                    $somMax = $somMax + ($crUE * 20);
                                    $juryUE = "";
                                    $mentionUE = "";
                                    if ($pointUE < 10 || $testUnite==true) {
                                        $juryUE = "Non validé";
                                        $echecUE = $echecUE + 1;
                                        $echecECUE = $echecECUE + $crUE;
                                        $creditInvalide = $creditInvalide+$crUE;
                                        
                                        //Historique de validation
                                       $c->historiquecr($crUE, $ligne->idEtudiant, $ligne->matricule, $l->idUE,$l->codeUE, $_SESSION['semestre'], $annee);
                                        //Fin
                                        
                                    } else{
                                        $juryUE = "Validée";
                                        //Historique de validation
                                        $c->historiquecrb($crUE, $ligne->idEtudiant, $ligne->matricule, $l->idUE,$l->codeUE, $_SESSION['semestre'], $annee);
                                        //Fin
                                    }

                                    $mentionUE = $c->mentions($pointUE);
                                    //$i = $i + 1;
                                    //Sous total
                                    //$i++;
                                    include 'controller/partie4Releve.php';
                                    //$i++;
                                    $ii++;
                                }
                                $rsMoyenne = round(($moyenneCumul / $somMax) * 20, 2);
                                $Mgen = $c->mentions($rsMoyenne);
                                $pourc = round(($rsMoyenne / 20) * 100, 2);
                                
                                $crSem=$somMax/20;
                                
                                if($creditInvalide==$crSem){
                                  $decision="NV";
                                }else if($creditInvalide<$crSem && $creditInvalide!=0){
                                  $decision="VP";
                                }else if($creditInvalide==0){ 
                                  $decision="VT";
                                }
								if($isVide==true) $decision="VP";
								
								//Les totaux
                                include 'controller/partie5Releve.php';
								
								
								
								//Ajout des Moyennes par Catégorie
								
								
								$i = $i + 1;

								if ($iCatA2 != 0) {
									$moyenneCA2 = round($somCatAsem2 / $iCatA2, 2);
								} else {
									$moyenneCA2 = 0; // ou une autre valeur par défaut ou un message d'erreur
								}
								
								if ($iCatB2 != 0) {
									$moyenneCB2 = round($somCatBsem2 / $iCatB2, 2);
								} else {
									$moyenneCB2 = 0; // ou une autre valeur par défaut ou un message d'erreur
								}								

								//Resultats 2

								$z->setCellValueByColumnAndRow(0, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 0);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));



								$z->setCellValueByColumnAndRow(1, $i, 'Moyenne Catégorie(A)');
								$style = $z->getStyleByColumnAndRow($i, 1);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(1, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
								$styleA1 = $z->getStyleByColumnAndRow(1, $i);
								$styleFont = $styleA1->getFont();
								$styleFont->setBold(true);
								$styleFont->setSize(10);

								$z->setCellValueByColumnAndRow(2, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 2);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(2, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
											
											
								if($isVide==true)
								$z->setCellValueByColumnAndRow(3, $i, "");
								else
								$z->setCellValueByColumnAndRow(3, $i, $moyenneCA2);
								$style = $z->getStyleByColumnAndRow($i, 3);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(3, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));

								$styleA1 = $z->getStyleByColumnAndRow(3, $i);
								$styleFont = $styleA1->getFont();
								$styleFont->setBold(true);
								$styleFont->setSize(10);


								$z->setCellValueByColumnAndRow(4, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 4);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));
								$bordure = $z->getStyleByColumnAndRow(4, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));


								$z->setCellValueByColumnAndRow(5, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 5);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(5, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));

								$z->setCellValueByColumnAndRow(6, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 6);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(6, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
											

								$z->setCellValueByColumnAndRow(7, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 7);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(7, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
								
								
								
								$i=$i+1;
								
								
								$z->setCellValueByColumnAndRow(0, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 0);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));



								$z->setCellValueByColumnAndRow(1, $i, 'Moyenne Catégorie(B)');
								$style = $z->getStyleByColumnAndRow($i, 1);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(1, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
								$styleA1 = $z->getStyleByColumnAndRow(1, $i);
								$styleFont = $styleA1->getFont();
								$styleFont->setBold(true);
								$styleFont->setSize(10);

								$z->setCellValueByColumnAndRow(2, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 2);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(2, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
											
											
								if($isVide==true)
								$z->setCellValueByColumnAndRow(3, $i, "");
								else
								$z->setCellValueByColumnAndRow(3, $i, $moyenneCB2);
								$style = $z->getStyleByColumnAndRow($i, 3);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(3, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));

								$styleA1 = $z->getStyleByColumnAndRow(3, $i);
								$styleFont = $styleA1->getFont();
								$styleFont->setBold(true);
								$styleFont->setSize(10);


								$z->setCellValueByColumnAndRow(4, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 4);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));
								$bordure = $z->getStyleByColumnAndRow(4, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));


								$z->setCellValueByColumnAndRow(5, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 5);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(5, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));

								$z->setCellValueByColumnAndRow(6, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 6);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(6, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
											

								$z->setCellValueByColumnAndRow(7, $i, '');
								$style = $z->getStyleByColumnAndRow($i, 7);
								$styleFont = $style->getFont()->applyFromArray(array(
									'bold' => true,
									'name' => 'Verdana',
									'color' => array(
										'rgb' => 'FF00FF00')
										));

								$bordure = $z->getStyleByColumnAndRow(7, $i);
								$bordure->applyFromArray(array(
									'borders' => array(
										'allborders' => array(
											'style' => PHPExcel_Style_Border::BORDER_THIN))));
								
								
								
								
								
								
								//Actualisation des sommes
								
								$somMoyenne=$somMoyenne+$rsMoyenne;
								$somMaximum=$somMaximum+$somMax;
								$somPoints=$somPoints+$moyenneCumul;
								$somCredit=$somCredit+$creditCapitalise;
								
								$totalCredit=$somMaximum/20;
								
								//Resultat Annuel
								
								include 'controller/partie6BulletinS2R.php';
								
								
								//Decision par  rapport à toute l'année
								$decision2="";
                                if($creditAnnuel==$totalCredit){
                                  $decision2="NV";
                                }else if($creditAnnuel <$totalCredit && $creditAnnuel!=0){
                                  $decision2="VP";
                                }else if($creditAnnuel==0){ 
                                  $decision2="VT";
                                }

								if($isVide2==true) $decision2="NV";
								
								//$moyAn=round($somMoyenne/2,2);
								//$moyAn=round($moyenAnnuel/2,2);
								if($isVide2==true) $moyAn=0;
                                $ment2=$c->mentions($moyAn);
                                $c->initialiserPalmares3($idEt, $annee, $moyAn, $ment2, $decision2, $_SESSION['semestre'], $creditAnnuel, $ligne->matricule);
								
								
                            }
							
                            $writer = new PHPExcel_Writer_Excel2007($workbook);
                            $records = './BulletinIndiv.xlsx';
                            $writer->save($records);
                            echo "<a href='BulletinIndiv.xlsx'><h3>Télécharger Fichier</a></h3>";
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include "parties/footer.php"; ?>
</div>       