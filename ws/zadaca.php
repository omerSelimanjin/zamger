<?

// WS/ZADACA - servisi za zadaću



function ws_zadaca() {
	global $userid, $user_student, $user_nastavnik, $user_studentska, $user_siteadmin;

	$rezultat = array( 'success' => 'true', 'data' => array() );
	
	if (isset($_REQUEST['student']))
		$student = intval($_REQUEST['student']);
	else
		$student = $userid;

	// Podaci o programskom jeziku
	if ($_REQUEST['akcija'] == "jezik") {
		$id = intval($_REQUEST['id']);
		$q10 = myquery("select * from programskijezik where id=$id");
		while ($dbrow = mysql_fetch_assoc($q10)) {
			array_push($rezultat['data'], $dbrow);
		}
	}
	
	// Podaci o jednoj zadaći
	else if ($_SERVER['REQUEST_METHOD'] == "GET" && isset($_GET['id'])) { // bilo = dajZadacu
		$id = intval($_GET['id']);
		if (!$user_siteadmin && !pravo_pristupa($id,0)) {
			print json_encode( array( 'success' => 'false', 'code' => 'ERR002', 'message' => 'Permission denied' ) );
			return;
		} else {
			$q10 = myquery("select * from zadaca where id=$id");
			while ($dbrow = mysql_fetch_assoc($q10)) {
				array_push($rezultat['data'], $dbrow);
			}
		}
	}

	// Vraća redni broj zadatka ako je dat filename
	else if ($_REQUEST['akcija'] == "dajZadatakIzFajla") {
		$zadaca = intval($_REQUEST['zadaca']);
		$filename = my_escape($_REQUEST['filename']);
		if (!$user_siteadmin && !pravo_pristupa($zadaca,$student)) {
			print json_encode( array( 'success' => 'false', 'code' => 'ERR002', 'message' => 'Permission denied' ) );
			return;
		} else {
			$q10 = myquery("select redni_broj from zadatak where zadaca=$zadaca and student=$student and filename='$filename' limit 1");
			while ($dbrow = mysql_fetch_assoc($q10)) {
				array_push($rezultat['data'], $dbrow);
			}
		}
	}

	// Postavlja status zadaće
	else if ($_SERVER['REQUEST_METHOD'] == "POST" && $_REQUEST['akcija'] == "status") {
		$zadaca = intval($_REQUEST['zadaca']);
		$zadatak = intval($_REQUEST['zadatak']);
		
		// Student sam ne može mijenjati status svojih zadaća
		if (!$user_siteadmin && !nastavnik_pravo_pristupa($zadaca)) {
			print json_encode( array( 'success' => 'false', 'code' => 'ERR002', 'message' => 'Permission denied' ) );
			return;
		} else {
			$komentar = my_escape($_REQUEST['komentar']);
			$izvjestaj_skripte = my_escape($_REQUEST['izvjestaj_skripte']);
			$status = intval($_REQUEST['status']);
			$bodova = floatval(str_replace(",",".",$_REQUEST['bodova']));
			$vrijeme = intval($_REQUEST['vrijeme']);
		
			// Filename
			$q90 = myquery("select filename from zadatak where zadaca=$zadaca and redni_broj=$zadatak and student=$student  order by id desc limit 1");
			$filename = mysql_result($q90,0,0);

			if ($vrijeme==0)
				$q100 = myquery("insert into zadatak set zadaca=$zadaca, redni_broj=$zadatak, student=$student, status=$status, bodova=$bodova, vrijeme=now(), komentar='$komentar', izvjestaj_skripte='$izvjestaj_skripte', filename='$filename', userid=$userid");
			else
				$q100 = myquery("insert into zadatak set zadaca=$zadaca, redni_broj=$zadatak, student=$student, status=$status, bodova=$bodova, vrijeme=FROM_UNIXTIME($vrijeme), komentar='$komentar', izvjestaj_skripte='$izvjestaj_skripte', filename='$filename', userid=$userid");

			// Odredjujemo ponudu kursa (za update komponente)
			$q110 = myquery("select pk.id from student_predmet as sp, ponudakursa as pk, zadaca as z where sp.student=$student and sp.predmet=pk.id and pk.predmet=z.predmet and pk.akademska_godina=z.akademska_godina and z.id=$zadaca");

			update_komponente($student, mysql_result($q110,0,0), $komponenta);

			zamgerlog("izmjena zadace (student u$student zadaca z$zadaca zadatak $zadatak)",2);
			$rezultat['message'] = "Ažuriran status zadaće";
		}
	}
	
	// Slanje zadaće
	else if ($_SERVER['REQUEST_METHOD'] == "POST") {
		$zadaca = intval($_REQUEST['zadaca']);
		$zadatak = intval($_REQUEST['zadatak']);
		if (!$user_siteadmin && !pravo_pristupa($zadaca, $student)) {
			print json_encode( array( 'success' => 'false', 'code' => 'ERR002', 'message' => 'Permission denied' ) );
			return;
		} 
		
		// Podaci o zadaći
		$q210 = myquery("select programskijezik, UNIX_TIMESTAMP(rok), attachment, naziv, komponenta, dozvoljene_ekstenzije, automatsko_testiranje, predmet, akademska_godina, zadataka, aktivna from zadaca where id=$zadaca");
		$jezik = mysql_result($q210,0,0);
		$rok = mysql_result($q210,0,1);
		$attach = mysql_result($q210,0,2);
		$naziv_zadace = mysql_result($q210,0,3);
		$komponenta = mysql_result($q210,0,4);
		$zadaca_dozvoljene_ekstenzije = mysql_result($q210,0,5);
		$automatsko_testiranje = mysql_result($q210,0,6);
		$predmet = mysql_result($q210,0,7);
		$ag = mysql_result($q210,0,8);
		$zadataka = mysql_result($q210,0,9);
		$aktivna = mysql_result($q210,0,10);
		
		if ($aktivna == 0 && !nastavnik_pravo_pristupa($zadaca)) {
			print json_encode( array( 'success' => 'false', 'code' => 'ERR912', 'message' => 'Zadaća nije aktivna' ) );
			return;
		}
		
		if ($zadatak < 1 || $zadatak > $zadataka) {
			print json_encode( array( 'success' => 'false', 'code' => 'ERR907', 'message' => 'Neispravan redni broj zadatka' ) );
			return;
		}

		// Provjera roka
		if ($rok <= time() && !nastavnik_pravo_pristupa($zadaca)) {
			print json_encode( array( 'success' => 'false', 'code' => 'ERR908', 'message' => 'Vrijeme za slanje zadaće je isteklo' ) );
			return;
		}
		
		$lokacijazadaca="$conf_files_path/zadace/$predmet-$ag/$student/";
		if (!file_exists("$conf_files_path/zadace/$predmet-$ag")) {
			mkdir ("$conf_files_path/zadace/$predmet-$ag",0777, true);
		}
		
		// Ako je aktivno automatsko testiranje, postavi status na 1 (automatska kontrola), inace na 4 (ceka pregled)
		if ($automatsko_testiranje==1) $prvi_status=1; else $prvi_status=4;

		// Prepisane zadaće se ne mogu ponovo slati
		$q240 = myquery("select status from zadatak where zadaca=$zadaca and redni_broj=$zadatak and student=$student order by id desc limit 1");
		if (mysql_num_rows($q240) > 0 && mysql_result($q240,0,0) == 2 && $userid == $student) { // status = 2 - prepisana zadaća
			print json_encode( array( 'success' => 'false', 'code' => 'ERR909', 'message' => 'Zadaća je prepisana i ne može se ponovo poslati' ) );
			return;
		}

		// Pravimo potrebne puteve
		if (!file_exists($lokacijazadaca)) mkdir ($lokacijazadaca,0777);
		if ($zadaca>0 && !file_exists("$lokacijazadaca$zadaca")) 
			mkdir ("$lokacijazadaca$zadaca",0777);
		
		// Temp fajl radi određivanja diff-a 
		if (file_exists("$lokacijazadaca$zadaca/difftemp")) 
			unlink ("$lokacijazadaca$zadaca/difftemp");

		$program = $_FILES['attachment']['tmp_name'];
		if ($program && (file_exists($program)) && $_FILES['attachment']['error']===UPLOAD_ERR_OK) {
			$ime_fajla = strip_tags(basename($_FILES['attachment']['name']));
			
			// Forsiramo ime fajla za non-attach
			if ($attach == 0) {
				$q220 = myquery("select ekstenzija from programskijezik where id=$jezik");
				$ekst = mysql_result($q220,0,0);
				$ime_fajla = $zadatak.$ekst;
			}

			// Ukidam HTML znakove radi potencijalnog XSSa
			$ime_fajla = str_replace("&", "", $ime_fajla);
			$ime_fajla = str_replace("\"", "", $ime_fajla);
			$puni_put = "$lokacijazadaca$zadaca/$ime_fajla";

			// Provjeravamo da li je ekstenzija na spisku dozvoljenih
			$ext = ".".pathinfo($ime_fajla, PATHINFO_EXTENSION); // FIXME: postojeći kod očekuje da ekstenzije počinju tačkom...
			$db_doz_eks = explode(',',$zadaca_dozvoljene_ekstenzije);
			if ($zadaca_dozvoljene_ekstenzije != "" && !in_array($ext, $db_doz_eks)) {
				print json_encode( array( 'success' => 'false', 'code' => 'ERR910', 'message' => 'Nedozvoljen tip datoteke' ) );
				return;
			}
			
			// Diffing
			$diff = "";
			$q255 = myquery("SELECT filename FROM zadatak WHERE zadaca=$zadaca AND redni_broj=$zadatak AND student=$student ORDER BY id DESC LIMIT 1");
			if (mysql_num_rows($q255) > 0) {
				$stari_filename = "$lokacijazadaca$zadaca/".mysql_result($q255, 0, 0);

				// Podržavamo diffing ako je i stara i nova ekstenzija ZIP (TODO ostale vrste arhiva)
				if (ends_with($stari_filename, ".zip") && ends_with($program, ".zip")) {
				
					// Pripremamo temp dir
					$zippath = "/tmp/difftemp";
					if (!file_exists($zippath)) {
						mkdir($zippath, 0777, true);
					} else if (!is_dir($zippath)) {
						unlink($zippath);
						mkdir($zippath);
					} else {
						rmMinusR($zippath);
					}
					$oldpath = "$zippath/old";
					$newpath = "$zippath/new";
					mkdir ($oldpath);
					mkdir ($newpath);
					`unzip -j "$stari_filename" -d $oldpath`;
					`unzip -j "$program" -d $newpath`;
					$diff = `/usr/bin/diff -ur $oldpath $newpath`;
					$diff = clear_unicode(my_escape($diff));
				} else {
					rename ($stari_filename, "$lokacijazadaca$zadaca/difftemp"); 
					$diff = `/usr/bin/diff -u $lokacijazadaca$zadaca/difftemp $program`;
					$diff = my_escape($diff);
					unlink ("$lokacijazadaca$zadaca/difftemp");
				}
			}
		
			if (file_exists($puni_put)) unlink ($puni_put);
			rename($program, $puni_put);
			chmod($puni_put, 0640);

			// Escaping za SQL
			$ime_fajla = my_escape($ime_fajla);

			$q260 = myquery("insert into zadatak set zadaca=$zadaca, redni_broj=$zadatak, student=$student, status=$prvi_status, vrijeme=now(), filename='$ime_fajla', userid=$userid");
			$id_zadatka = mysql_insert_id();

			if (strlen($diff)>1) {
				$q270 = myquery("insert into zadatakdiff set zadatak=$id_zadatka, diff='$diff'");
			}
			$rezultat['message'] = "Zadaća uspješno poslana";
			zamgerlog("poslana zadaca z$zadaca zadatak $zadatak (webservice)",2); // nivo 2 - edit
			zamgerlog2("poslana zadaca (webservice)", $zadaca, $zadatak);
		} else {
			zamgerlog("greska pri slanju zadace (zadaca z$zadaca zadatak $zadatak - webservice)",3);
			zamgerlog2("greska pri slanju zadace (webservice)", $zadaca, $zadatak);
			print json_encode( array( 'success' => 'false', 'code' => 'ERR911', 'message' => 'Slanje zadaće nije uspjelo. Molimo pokušajte ponovo' ) );
			return;
		}
	}

	
	// Default akcija: spisak zadaća koje su vidljive studentu u tekućoj akademskoj godini, sa bodovima
	else {
		if (isset($_REQUEST['ag']))
			$ag = intval($_REQUEST['ag']);
		else {
			$q10 = myquery("select id from akademska_godina where aktuelna=1 order by id desc limit 1");
			$ag = mysql_result($q10,0,0);
		}
		
		$rezultat['data']['predmeti'] = array();
		$q100 = myquery("select p.id, p.naziv, p.kratki_naziv from student_predmet as sp, ponudakursa as pk, predmet as p where sp.student=$student and sp.predmet=pk.id and pk.akademska_godina=$ag and pk.predmet=p.id");
		while ($r100 = mysql_fetch_row($q100)) {
			$predmet = array();
			$predmet['id'] = $r100[0];
			$predmet['naziv'] = $r100[1];
			$predmet['kratki_naziv'] = $r100[2];
			$predmet['zadace'] = array();
			
			$q110 = myquery("select id, naziv, bodova, zadataka, programskijezik, attachment, postavka_zadace, UNIX_TIMESTAMP(rok), aktivna from zadaca where predmet=$r100[0] and akademska_godina=$ag order by komponenta,id");
			while ($r110 = mysql_fetch_row($q110)) {
				$zadaca = array();
				$zadaca['id'] = $r110[0];
				$zadaca['naziv'] = $r110[1];
				$zadaca['bodova'] = 0;
				$zadaca['moguce_bodova'] = $r110[2];
				$zadaca['broj_zadataka'] = $r110[3];
				$zadaca['programski_jezik'] = $r110[4];
				if ($r110[5]==1) $zadaca['attachment'] = 'true'; else $zadaca['attachment'] = 'false';
				$zadaca['rok'] = $r110[7];
				if ($r110[8]==1) $zadaca['aktivna'] = 'true'; else $zadaca['aktivna'] = 'false';
				
				$zadaca['zadaci'] = array();
				for ($zadatak=1;$zadatak<=$r110[3];$zadatak++) {
					$zad_ar = array();
					$zad_ar['redni_broj'] = $zadatak;
					// Uzmi samo rjesenje sa zadnjim IDom
					$q22 = myquery("select status, bodova, komentar, izvjestaj_skripte, vrijeme, filename from zadatak where student=$student and zadaca=$r110[0] and redni_broj=$zadatak order by id desc limit 1");
					if (mysql_num_rows($q22)<1) {
						$zad_ar['poslan'] = 'false';
					} else {
						$zad_ar['poslan'] = 'true';
						$zad_ar['status'] = mysql_result($q22,0,0);
						$zad_ar['bodova'] = mysql_result($q22,0,1);
						$zad_ar['komentar_tutora'] = mysql_result($q22,0,2);
						$zad_ar['izvjestaj_skripte'] = mysql_result($q22,0,3);
						$zad_ar['vrijeme_slanja'] = mysql_result($q22,0,4);
						$zad_ar['filename'] = mysql_result($q22,0,5);
						$zadaca['bodova'] += $zad_ar['bodova'];
					}
					$zadaca['zadaci'][] = $zad_ar;
				}
				
				$predmet['zadace'][] = $zadaca;
			}
			$rezultat['data']['predmeti'][] = $predmet;
		}
	}


	print json_encode($rezultat);
}

// Da li korisnik $userid ima pravo pristupa zadaći $zadaca za studenta $student
// Ako je $student==0 onda se podatak odnosi na sve studente (read-only pristup)
function pravo_pristupa($zadaca, $student=0) {
	global $userid;
	
	// Korisnik ima pravo pristupa svojim zadaćama
	// Student ima pravo pristupa podacima zadaće na predmetima koje sluša
	if (($student == $userid || $student == 0) && student_pravo_pristupa($zadaca)) return true;

	// Nastavnici i super-asistenti mogu pristupati svemu
	// Asistent može pristupiti postavci zadaće
	$privilegija = nastavnik_pravo_pristupa($zadaca);
	if ($privilegija === false) return false;
	if ($student==0 || $privilegija != "asistent") return true;
	
	// Za asistente provjeravamo ograničenja na labgrupe
	return nastavnik_ogranicenje($zadaca, $student);
}


function student_pravo_pristupa($zadaca) {
	global $userid;

	$q20 = myquery("SELECT COUNT(*) FROM student_predmet as sp, zadaca as z, ponudakursa as pk WHERE sp.student=$userid AND sp.predmet=pk.id AND pk.predmet=z.predmet AND pk.akademska_godina=z.akademska_godina AND z.id=$zadaca");
	if (mysql_result($q20,0,0) > 0) return true;
	return false;
}

function nastavnik_pravo_pristupa($zadaca) {
	global $userid;

	// Da li korisnik ima pravo ući u grupu?
	$q40 = myquery("select np.nivo_pristupa from nastavnik_predmet as np, zadaca as z where np.nastavnik=$userid and np.predmet=z.predmet and np.akademska_godina=z.akademska_godina and z.id=$zadaca");
	if (mysql_num_rows($q40)<1) {
		// Nastavnik nije angažovan na predmetu
		return false;
	}
	return mysql_result($q40,0,0);
}

function nastavnik_ogranicenje($zadaca, $student) {
	global $userid;

	$q45 = myquery("select l.id from student_labgrupa as sl, labgrupa as l, zadaca as z where sl.student=$student and sl.labgrupa=l.id and l.predmet=z.predmet and l.akademska_godina=z.akademska_godina and l.virtualna=0 and z.id=$zadaca");
	$q50 = myquery("select o.labgrupa from ogranicenje as o, labgrupa as l, zadaca as z where o.nastavnik=$userid and o.labgrupa=l.id and l.predmet=z.predmet and l.akademska_godina=z.akademska_godina and l.virtualna=0 and z.id=$zadaca");
	if (mysql_num_rows($q45)<1) {
		if (mysql_num_rows($q50)>0) {
			// imate ogranicenja a student nije u grupi
			return false;
		}
		return true;
	}
	$labgrupa = mysql_result($q45,0,0);

	if (mysql_num_rows($q50)>0) {
		$nasao=0;
		while ($r50 = mysql_fetch_row($q50)) {
			if ($r50[0] == $labgrupa) { $nasao=1; break; }
		}
		if ($nasao == 0) {
			// echo "FAIL|ogranicenje na labgrupu $labgrupa";
			return false;
		}
	}
	return true;
}


?>
