<h2>Zahtjev za promjenu ličnih podataka u Informacionom sistemu <?=$conf_skr_naziv_institucije_genitiv?></h2>
<?

if ($_POST['subakcija'] == "potvrda" && check_csrf_token()) {
	// Da li je u pitanju izmjena ili brisanje maila
	$q1000 = myquery("select id, adresa, sistemska from email where osoba=$userid");
	while ($r1000 = mysql_fetch_row($q1000)) {
		if ($_POST['obrisi_email'.$r1000[0]]) {
			$q1010 = myquery("DELETE FROM email WHERE id=$r1000[0]");
			nicemessage("E-mail adresa obrisana.");
			print "<a href=\"javascript:history.go(-1);\">Nazad</a>";
			zamgerlog("obrisana email adresa ".$r1000[1],2);
			return 0;
		}
		if ($_POST['izmijeni_email'.$r1000[0]]) {
			// Validacija maila
			$email = my_escape($_POST['email'.$r1000[0]]);
			if (!preg_match("/\w/", $email)) {
				niceerror("Promjena adrese nije uspjela. Unijeli ste praznu e-mail adresu.");
				print "<p>Ako želite da obrišete adresu, koristite dugme \"Obriši\".</p>";
				print "<a href=\"javascript:history.go(-1);\">Nazad</a>";
				return 0;
			}

			if (function_exists('filter_var')) {
				if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
					niceerror("Nova e-mail adresa nije ispravna.");
					print "<a href=\"javascript:history.go(-1);\">Nazad</a>";
					return 0;
				}
			} else {
				if (!strstr($email, "@")) {
					niceerror("Nova e-mail adresa nije ispravna.");
					print "<a href=\"javascript:history.go(-1);\">Nazad</a>";
					return 0;
				}
			}

			$q1020 = myquery("update email set adresa='$email' where id=".$r1000[0]);
			nicemessage("E-mail adresa promijenjena.");
			print "<a href=\"javascript:history.go(-1);\">Nazad</a>";
			zamgerlog("email adresa promijenjena iz ".$r1000[1]." u ".$email,2);
			return 0;
		}
	}

	if ($_POST['dodaj_email']) {
		// Validacija maila
		$email = my_escape($_REQUEST['email_novi']);

		if (!preg_match("/\w/", $email)) {
			niceerror("Dodavanje adrese nije uspjelo. Unijeli ste praznu e-mail adresu.");
			print "<a href=\"javascript:history.go(-1);\">Nazad</a>";
			return 0;
		}

		if (function_exists('filter_var')) {
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				niceerror("Nova e-mail adresa nije ispravna.");
				print "<a href=\"javascript:history.go(-1);\">Nazad</a>";
				return 0;
			}
		} else {
			if (!strstr($email, "@")) {
				niceerror("Nova e-mail adresa nije ispravna.");
				print "<a href=\"javascript:history.go(-1);\">Nazad</a>";
				return 0;
			}
		}

		$q1030 = myquery("INSERT INTO email SET osoba=$userid, adresa='$email', sistemska=0");
		nicemessage("E-mail adresa dodana.");
		print "<a href=\"javascript:history.go(-1);\">Nazad</a>";
		zamgerlog("dodana nova email adresa ".$email,2);
		return 0;
		
	}

	$ime = my_escape($_REQUEST['ime']);
	$prezime = my_escape($_REQUEST['prezime']);
	$spol = $_REQUEST['spol'];
	if ($spol != "M" && $spol != "Z") $spol="";
	$brindexa = my_escape($_REQUEST['brindexa']);
	$jmbg = my_escape($_REQUEST['jmbg']);

	$adresa = my_escape($_REQUEST['adresa']);
	$adresa_mjesto = my_escape($_REQUEST['adresa_mjesto']);
	$telefon = my_escape($_REQUEST['telefon']);

	$djevojacko_prezime = my_escape($_REQUEST['djevojacko_prezime']);
	$imeoca = my_escape($_REQUEST['imeoca']);
	$prezimeoca = my_escape($_REQUEST['prezimeoca']);
	$imemajke = my_escape($_REQUEST['imemajke']);
	$prezimemajke = my_escape($_REQUEST['prezimemajke']);
	$mjesto_rodjenja = my_escape($_REQUEST['mjesto_rodjenja']);
	$opcina_rodjenja = intval($_REQUEST['opcina_rodjenja']);
	$drzava_rodjenja = intval($_REQUEST['drzava_rodjenja']);
	$nacionalnost = intval($_REQUEST['nacionalnost']);
	$drzavljanstvo = intval($_REQUEST['drzavljanstvo']);
	$kanton = intval($_REQUEST['_lv_column_kanton']);
	if ($_REQUEST['borac']) $borac=1; else $borac=0;

	if (preg_match("/(\d+).*?(\d+).*?(\d+)/", $_REQUEST['datum_rodjenja'], $matches)) {
		$dan=$matches[1]; $mjesec=$matches[2]; $godina=$matches[3];
		if ($godina<100)
			if ($godina<50) $godina+=2000; else $godina+=1900;
		if ($godina<1000)
			if ($godina<900) $godina+=2000; else $godina+=1000;
	} else {
		$dan="00"; $mjesec="00"; $godina="0000";
	}

	// Mjesto
	$mjrid=0;
	if ($mjesto_rodjenja != "") {
		$q1 = myquery("select id from mjesto where naziv='$mjesto_rodjenja' and opcina=$opcina_rodjenja and drzava=$drzava_rodjenja");
		if (mysql_num_rows($q1)<1) {
			$q2 = myquery("insert into mjesto set naziv='$mjesto_rodjenja', opcina=$opcina_rodjenja, drzava=$drzava_rodjenja");
			$q1 = myquery("select id from mjesto where naziv='$mjesto_rodjenja' and opcina=$opcina_rodjenja and drzava=$drzava_rodjenja");
			zamgerlog("upisano novo mjesto rodjenja $mjesto_rodjenja", 2);
		}
		$mjrid = mysql_result($q1,0,0);
	}

	$admid=0;
	if ($adresa_mjesto != "") {
		$q3 = myquery("select id from mjesto where naziv='$adresa_mjesto'");
		if (mysql_num_rows($q3)<1) {
			$q4 = myquery("insert into mjesto set naziv='$adresa_mjesto'");
			$q3 = myquery("select id from mjesto where naziv='$adresa_mjesto'");
			zamgerlog("upisano novo mjesto (adresa) $adresa_mjesto", 2);
		}
		$admid = mysql_result($q3,0,0);
	}


	// Da li je uopste bilo promjene?
	$q05 = myquery("select ime, prezime, imeoca, prezimeoca, imemajke, prezimemajke, spol, brindexa, datum_rodjenja, mjesto_rodjenja, nacionalnost, drzavljanstvo, jmbg, adresa, adresa_mjesto, telefon, kanton, boracke_kategorije, djevojacko_prezime from osoba where id=$userid");
	if (mysql_result($q05,0,0)==$ime && mysql_result($q05,0,1)==$prezime && mysql_result($q05,0,2)==$imeoca && mysql_result($q05,0,3)==$prezimeoca && mysql_result($q05,0,4)==$imemajke && mysql_result($q05,0,5)==$prezimemajke && mysql_result($q05,0,6)==$spol && mysql_result($q05,0,7)==$brindexa && mysql_result($q05,0,8)=="$godina-$mjesec-$dan" && mysql_result($q05,0,9)==$mjrid && mysql_result($q05,0,10)==$nacionalnost && mysql_result($q05,0,11)==$drzavljanstvo && mysql_result($q05,0,12)==$jmbg && mysql_result($q05,0,13)==$adresa && mysql_result($q05,0,14)==$admid && mysql_result($q05,0,15)==$telefon && mysql_result($q05,0,16)==$kanton && mysql_result($q05,0,17)==$borac && mysql_result($q05,0,18)==$djevojacko_prezime) {
		?><p><b>Ništa nije promijenjeno?</b><br>
		Podaci koje ste unijeli ne razlikuju se od podataka koje već imamo u bazi. Zahtjev za promjenu neće biti poslan.</p><?
		return;
	}

	$q10 = myquery("select id from promjena_podataka where osoba=$userid");
	if (mysql_num_rows($q10)>0) {
		$id = mysql_result($q10,0,0);
		$upit = "osoba=$userid";
		if ($ime != "") $upit .= ", ime='$ime'";
		if ($prezime != "") $upit .= ", prezime='$prezime'";
		if ($imeoca != "") $upit .= ", imeoca='$imeoca'";
		if ($prezimeoca != "") $upit .= ", prezimeoca='$prezimeoca'";
		if ($imemajke != "") $upit .= ", imemajke='$imemajke'";
		if ($prezimemajke != "") $upit .= ", prezimemajke='$prezimemajke'";
		if ($spol != "") $upit .= ", spol='$spol'";
		if ($brindexa != "") $upit .= ", brindexa='$brindexa'";
		if ($jmbg != "") $upit .= ", jmbg='$jmbg'";
		if ($mjrid != 0) $upit .= ", mjesto_rodjenja=$mjrid";
		if ($nacionalnost != 0) $upit .= ", nacionalnost=$nacionalnost";
		if ($drzavljanstvo != 0) $upit .= ", drzavljanstvo=$drzavljanstvo";
		if ($adresa != "") $upit .= ", adresa='$adresa'";
		if ($admid != 0) $upit .= ", adresa_mjesto='$admid'";
		if ($telefon != "") $upit .= ", telefon='$telefon'";
		if ($email != "") $upit .= ", email='$email'";
		if ($kanton != 0) $upit .= ", kanton=$kanton";
		if ($godina!=1970) $upit .= ", datum_rodjenja='$godina-$mjesec-$dan'";
		if ($borac != 0) $upit .= ", boracke_kategorije=$borac";
		if ($djevojacko_prezime != "") $upit .= ", djevojacko_prezime='$djevojacko_prezime'";
		$q20 = myquery("update promjena_podataka set $upit where id=$id");
	} else {
		$q25 = myquery("select slika from osoba where id=$userid");
		$slika = mysql_result($q25,0,0);
		$q30 = myquery("insert into promjena_podataka set osoba=$userid, ime='$ime', prezime='$prezime', imeoca='$imeoca', prezimeoca='$prezimeoca', imemajke='$imemajke', prezimemajke='$prezimemajke', spol='$spol', brindexa='$brindexa', jmbg='$jmbg', mjesto_rodjenja=$mjrid, nacionalnost=$nacionalnost, drzavljanstvo=$drzavljanstvo, adresa='$adresa', adresa_mjesto=$admid, telefon='$telefon', kanton=$kanton, datum_rodjenja='$godina-$mjesec-$dan', boracke_kategorije=$borac, slika='".my_escape($slika)."'");
	}
	zamgerlog("zatrazena promjena ličnih podataka",2); // 2 = edit

	?>
	<h2>Zahvaljujemo!</h2>

	<p>Zahtjev je poslan!</p>
	<p>Nakon što Studentska služba provjeri ispravnost podataka, oni će biti uneseni u Informacioni sistem. Molimo da budete dostupni za slučaj da je potrebna dokumentacija za neku od izmjena koje ste zatražili.</p>
	<?
	return;
}


// Postavljanje ili promjena slike

if ($_POST['subakcija']=="postavisliku" && check_csrf_token()) {
	$slika = $_FILES['slika']['tmp_name'];
	if ($slika && (file_exists($slika))) {
		// Kopiramo novu sliku na privremenu lokaciju
		$podaci = getimagesize($slika);
		$koef = $podaci[0]/$podaci[1];
		if ($koef < 0.5 || $koef > 2) {
			niceerror("Omjer širine i visine slike nije povoljan.");
			print "<p>Slika bi trebala biti uobičajenog formata slike za lične dokumente. Ova je formata $podaci[0]x$podaci[1].</p>\n";
			return;
		}

		$novavisina = 150;
		$novasirina = $novavisina * $koef;
		$filename = "$conf_files_path/slike/$userid-promjena";
		if (!file_exists("$conf_files_path/slike"))
			mkdir ("$conf_files_path/slike", 0777, true);

		$dest = imagecreatetruecolor($novasirina, $novavisina);
		switch ($podaci[2]) {
			case IMAGETYPE_GIF:
				$source = imagecreatefromgif($slika);
				imagecopyresampled($dest, $source, 0, 0, 0, 0, $novasirina, $novavisina, $podaci[0], $podaci[1]);
				imagegif($dest, $filename.".gif");
				$slikabaza = "$userid-promjena.gif";
				break;
			case IMAGETYPE_JPEG:
				$source = imagecreatefromjpeg($slika);
				imagecopyresampled($dest, $source, 0, 0, 0, 0, $novasirina, $novavisina, $podaci[0], $podaci[1]);
				imagejpeg($dest, $filename.".jpg");
				$slikabaza = "$userid-promjena.jpg";
				break;
			case IMAGETYPE_PNG:
				$source = imagecreatefrompng($slika);
				imagecopyresampled($dest, $source, 0, 0, 0, 0, $novasirina, $novavisina, $podaci[0], $podaci[1]);
				imagepng($dest, $filename.".png");
				$slikabaza = "$userid-promjena.png";
				break;
			case IMAGETYPE_TIFF_II:
				nicemessage("Nije moguća promjena dimenzija slike tipa TIFF... Ostavljam zadate dimenzije.");
				rename ($slika, $filename.".tiff");
				$slikabaza = "$userid-promjena.tiff";
				break;
			default:
				niceerror("Nepoznat tip slike.");
				print "<p>Za vašu profil sliku možete koristiti samo slike tipa GIF, JPEG ili PNG.</p>";
				return;
		}
	
		$q300 = myquery("select id from promjena_podataka where osoba=$userid");
		if (mysql_num_rows($q300)>0) {
			$q310 = myquery("update promjena_podataka set slika='$slikabaza' where osoba=$userid");
		} else {
			$q320 = myquery("insert into promjena_podataka select 0, $userid, ime, prezime, imeoca, prezimeoca, imemajke, prezimemajke, spol, email, brindexa, datum_rodjenja, mjesto_rodjenja, nacionalnost, drzavljanstvo, boracke_kategorije, jmbg, adresa, adresa_mjesto, telefon, kanton, '$slikabaza', NOW() from osoba where id=$userid");
		}
	
		zamgerlog("zatrazeno postavljanje/promjena slike", 2);
		?>
		<h2>Zahvaljujemo!</h2>
	
		<p>Zahtjev je poslan!</p>
		<p>Nakon što Studentska služba provjeri ispravnost podataka, oni će biti uneseni u Informacioni sistem. Molimo da budete dostupni za slučaj da je potrebna dokumentacija za neku od izmjena koje ste zatražili.</p>
		<?
		return;
	} else {
		nicemessage("Greška pri slanju slike");
	}
}


// Brisanje slike

if ($_POST['subakcija']=="obrisisliku" && check_csrf_token()) {
	$q300 = myquery("select id from promjena_podataka where osoba=$userid");
	if (mysql_num_rows($q300)>0) {
		$q310 = myquery("update promjena_podataka set slika='' where osoba=$userid");
	} else {
		$q320 = myquery("insert into promjena_podataka select 0, $userid, ime, prezime, imeoca, prezimeoca, imemajke, prezimemajke, spol, brindexa, datum_rodjenja, mjesto_rodjenja, nacionalnost, drzavljanstvo, boracke_kategorije, jmbg, adresa, adresa_mjesto, telefon, kanton, '', NOW() from osoba where id=$userid");
	}

	zamgerlog("zatrazeno brisanje slike", 2);
	?>
	<h2>Zahvaljujemo!</h2>

	<p>Zahtjev je poslan!</p>
	<p>Nakon što Službe <?=$conf_skr_naziv_institucije_genitiv?> provjere ispravnost podataka, oni će biti uneseni u Informacioni sistem. Molimo da budete dostupni za slučaj da je potrebna dokumentacija za neku od izmjena koje ste zatražili.</p>
	<?
	return;
}



if ($conf_system_auth == "ldap") {
?>
<h3><font color="red">NAPOMENA:</font> Pristupnu šifru možete promijeniti isključivo koristeći <?=$conf_promjena_sifre?></h3>
<?

} else {
	// TODO: napraviti promjenu sifre

}

$q390 = myquery("select UNIX_TIMESTAMP(vrijeme_zahtjeva) from promjena_podataka where osoba=$userid order by vrijeme_zahtjeva desc limit 1");

if (mysql_num_rows($q390)>0) {
	?><p><b>Već ste uputili zahtjev za promjenu ličnih podataka</b> (na dan <?=date("d. m. Y. \u H:i:s", mysql_result($q390,0,0))?>). Vaš zahtjev se trenutno razmatra. U međuvremenu, ispod možete vidjeti stare podatke i eventualno ponovo poslati zahtjev (stari zahtjev će u tom slučaju biti zanemaren.</p><?
} else {
?>
	<p>Pozivamo Vas da podržite rad Studentske službe <?=$conf_skr_naziv_institucije_genitiv?> tako što ćete prijaviti sve eventualne greške u vašim ličnim podacima (datim ispod).</p><?
}

$q400 = myquery("select ime, prezime, brindexa, UNIX_TIMESTAMP(datum_rodjenja), mjesto_rodjenja, jmbg, drzavljanstvo, adresa, adresa_mjesto, telefon, kanton, spol, imeoca, prezimeoca, imemajke, prezimemajke, drzavljanstvo, nacionalnost, boracke_kategorije, slika, djevojacko_prezime from osoba where id=$userid");

// Spisak gradova
$q410 = myquery("select id,naziv, opcina, drzava from mjesto order by naziv");
$gradovir="<option></option>";
$gradovia="<option></option>";
while ($r410 = mysql_fetch_row($q410)) { 
	$gradovir .= "<option"; $gradovia .= "<option";
 	if ($r410[0]==mysql_result($q400,0,4)) { 
		$gradovir  .= " SELECTED"; 
		$mjestorvalue = $r410[1]; 
		$opcinar = $r410[2];
		$drzavar = $r410[3];
	}
 	if ($r410[0]==mysql_result($q400,0,8)) { $gradovia  .= " SELECTED"; $adresarvalue = $r410[1]; }
	$gradovir .= ">$r410[1]</option>\n";
	$gradovia .= ">$r410[1]</option>\n";
}


// Spisak opcina
$q420 = myquery("select id,naziv from opcina order by naziv");
$opciner="<option></option>";
while ($r420 = mysql_fetch_row($q420)) {
	$opciner .= "<option value=\"$r420[0]\"";
 	if ($r420[0]==$opcinar) { $opciner  .= " SELECTED";  }
	$opciner .= ">$r420[1]</option>\n";
}


// Spisak drzava
$q430 = myquery("select id,naziv from drzava order by naziv");
$drzaver="<option></option>";
$drzavlj="<option></option>";
while ($r430 = mysql_fetch_row($q430)) {
	$drzaver .= "<option value=\"$r430[0]\"";
 	if ($r430[0]==$drzavar) { $drzaver  .= " SELECTED";  }
	$drzaver .= ">$r430[1]</option>\n";
	$drzavlj .= "<option value=\"$r430[0]\"";
 	if ($r430[0]==mysql_result($q400,0,16)) { $drzavlj  .= " SELECTED";  }
	$drzavlj .= ">$r430[1]</option>\n";
}


// Spisak nacionalnosti
$q440 = myquery("select id,naziv from nacionalnost order by naziv");
$nacion="<option></option>";
while ($r440 = mysql_fetch_row($q440)) {
	$nacion .= "<option value=\"$r440[0]\"";
 	if ($r440[0]==mysql_result($q400,0,17)) { $nacion  .= " SELECTED";  }
	$nacion .= ">$r440[1]</option>\n";
}

// Spol
if (mysql_result($q400,0,11)=="M") $muskir = "CHECKED"; else $muskir="";
if (mysql_result($q400,0,11)=="Z") $zenskir = "CHECKED"; else $zenskir="";

// Pripadnik borackih kategorija
if (mysql_result($q400,0,18)==1) $boracke = "CHECKED"; else $boracke="";


// Određujemo tekst poruke u kategoriji "Lični podaci"

if ($user_nastavnik || $user_studentska) {
	$tekst_licni_podaci = "Molimo da popunite dodatne lične podatke potrebne za sistem upravljanja ljudskim resursima. Ovim putem preuzimate punu odgovornost za ispravnost i redovno ažuriranje podataka koje navedete u formularu ispod.";
} else {
	$tekst_licni_podaci = "Ovi podaci će se koristiti za automatsko popunjavanje formulara i obrazaca. Podaci su preuzeti iz formulara koje ste popunili prilikom upisa na fakultet. Ovim putem preuzimate punu odgovornost za ispravnost podataka koje navedete u formularu ispod.";
}

?>
	<script type="text/javascript">
	function comboBoxEdit(evt, elname) {
		var ib = document.getElementById(elname);
		var list = document.getElementById("comboBoxDiv_"+elname);
		var listsel = document.getElementById("comboBoxMenu_"+elname);

		var key, keycode;
		if (evt) {
			key = evt.which;
			keycode = evt.keyCode;
		} else if (window.event) {
			key = window.event.keyCode;
			keycode = key; // wtf?
		} else return true;

		if (keycode==40) { // arrow down
			if (list.style.visibility == 'visible') {
				if (listsel.selectedIndex<listsel.length)
					listsel.selectedIndex = listsel.selectedIndex+1;
			} else {
				comboBoxShowHide(elname);
			}
			return false;

		} else if (keycode==38) { // arrow up
			if (list.style.visibility == 'visible' && listsel.selectedIndex>0) {
				listsel.selectedIndex = listsel.selectedIndex-1;
			}
			return false;

		} else if (keycode==13 && list.style.visibility == 'visible') { // Enter key - select option and hide
			comboBoxOptionSelected(elname);
			return false;

		} else if (key>31 && key<127) {
			// This executes before the letter is added to text
			// so we have to add it manually
			var ibtxt = ib.value.toLowerCase() + String.fromCharCode(key).toLowerCase();

			for (i=0; i<listsel.length; i++) {
				var listtxt = listsel.options[i].value.toLowerCase();
				if (ibtxt == listtxt.substr(0,ibtxt.length)) {
					listsel.selectedIndex=i;
					if (list.style.visibility == 'hidden') comboBoxShowHide(elname);
					return true;
				}
			}
			return true;
		}
		return true;
	}

	function comboBoxShowHide(elname) {
		var ib = document.getElementById(elname);
		var list = document.getElementById("comboBoxDiv_"+elname);
		var image = document.getElementById("comboBoxImg_"+elname);

		if (list.style.visibility == 'hidden') {
			// Nadji poziciju objekta
			var curleft = curtop = 0;
			var obj=ib;
			if (obj.offsetParent) {
				do {
					curleft += obj.offsetLeft;
					curtop += obj.offsetTop;
				} while (obj = obj.offsetParent);
			}
	
			list.style.visibility = 'visible';
			list.style.left=curleft;
			list.style.top=curtop+ib.offsetHeight;
			image.src = "images/cb_down.png";
		} else {
			list.style.visibility = 'hidden';
			image.src = "images/cb_up.png";
		}
	}
	function comboBoxHide(elname) {
		var list = document.getElementById("comboBoxDiv_"+elname);
		var listsel = document.getElementById("comboBoxMenu_"+elname);
		if (list.style.visibility == 'visible' && listsel.focused==false) {
			list.style.visibility = 'hidden';
			image.src = "images/cb_up.png";
		}
	}
	function comboBoxOptionSelected(elname) {
		var ib = document.getElementById(elname);
		var listsel = document.getElementById("comboBoxMenu_"+elname);
		
		ib.value = listsel.options[listsel.selectedIndex].value;
		comboBoxShowHide(elname);
	}
	</script>

	<!--script type="text/javascript" src="js/combo-box.js"></script-->


	<table border="0" width="600">
	<tr><td colspan="2" bgcolor="#999999"><font color="#FFFFFF">SLIKA:</font></td></tr>
	<tr><td colspan="2">Slika koju ovdje odaberete nije vaš "avatar" nego zvanična fotografija u formatu lične karte / pasoša koja ide u dokumentaciju fakulteta i vezuje se za vaše zvanične dokumente. Slika mora imati bijelu/svijetlu pozadinu. Molimo vas da pošaljete sliku zadovoljavajuće kvalitete radi lakšeg štampanja dokumenata.</td></tr>
	<?
	if (mysql_result($q400,0,19)=="") {
		print genform("POST", "a\"  enctype=\"multipart/form-data");
		?>
		<input type="hidden" name="subakcija" value="postavisliku">
		<tr><td colspan="2"><p><input type="file" name="slika"> <input type="submit" value="Dodaj sliku"></p></td></tr>
		</form>
		<?
	} else {
		?>
		<?=genform("POST")?>
		<input type="hidden" name="subakcija" value="obrisisliku">
		<tr><td colspan="2"><p>
		<img src="?sta=common/slika&osoba=<?=$userid?>"><br/>
		<input type="submit" value="Obriši sliku"><br></form>
		<?
		print genform("POST", "b\"  enctype=\"multipart/form-data");
		?>
		<input type="hidden" name="subakcija" value="postavisliku">
		<input type="file" name="slika"> <input type="submit" value="Promijeni sliku"></p></td></tr>
		</form>
		<?
	}
	?>
	<?=genform("POST")?>
	<tr><td colspan="2">&nbsp;</td></tr>
	<tr><td colspan="2" bgcolor="#999999"><font color="#FFFFFF">OSNOVNI PODACI:</font></td></tr>
	<tr><td>
		Ime:</td><td><input type="text" name="ime" value="<?=mysql_result($q400,0,0)?>" class="default">
	</td></tr>
	<tr><td>
		Prezime:</td><td><input type="text" name="prezime" value="<?=mysql_result($q400,0,1)?>" class="default">
	</td></tr>
	<tr><td>
		Spol:</td><td><input type="radio" name="spol" value="M" <?=$muskir?>> Muški &nbsp;&nbsp; <input type="radio" name="spol" value="Z" <?=$zenskir?>> Ženski
	</td></tr>

	<? if ($user_student) { ?>
	<tr><td>
		Broj indexa:</td><td><input type="text" name="brindexa" value="<?=mysql_result($q400,0,2)?>" class="default">
	</td></tr>
	<? } ?>
	<tr><td>
		JMBG:</td><td><input type="text" name="jmbg" value="<?=mysql_result($q400,0,5)?>" class="default">
	</td></tr>

	<tr><td colspan="2">&nbsp;</td></tr>
	<tr><td colspan="2" bgcolor="#999999"><font color="#FFFFFF">KONTAKT PODACI:</font></td></tr>
	<tr><td colspan="2">Ovi podaci će se koristiti kako bi Službe <?=$conf_skr_naziv_institucije_genitiv?> mogle lakše stupiti u kontakt s vama.</td></tr>

	</td></tr>
	<tr><td>
		Adresa (ulica i broj):</td><td><input type="text" name="adresa" value="<?=mysql_result($q400,0,7)?>" class="default">
	</td></tr>
	<tr><td>
		Adresa (mjesto):</td><td>
		<input type="text" name="adresa_mjesto" id="adresa_mjesto" value="<?=$adresarvalue?>" class="default" onKeyPress="comboBoxEdit(event, 'adresa_mjesto')" autocomplete="off" onBlur="comboBoxHide('adresa_mjesto')"><img src="images/cb_up.png" width="19" height="18" onClick="comboBoxShowHide('adresa_mjesto')" id="comboBoxImg_adresa_mjesto" valign="bottom"> <img src="images/cb_down.png" style="visibility:hidden">
		<!-- Rezultati pretrage primaoca -->
		<div id="comboBoxDiv_adresa_mjesto" style="position:absolute;visibility:hidden">
			<select name="comboBoxMenu_adresa_mjesto" id="comboBoxMenu_adresa_mjesto" size="10" onClick="comboBoxOptionSelected('adresa_mjesto')"><?=$gradovir?></select>
		</div>
	</td></tr>
	<tr><td>
		Kontakt telefon:</td><td><input type="text" name="telefon" value="<?=mysql_result($q400,0,9)?>" class="default">
	</td></tr>
	<?
	$q450 = myquery("select id, adresa, sistemska from email where osoba=$userid");
	?>
	<tr><td valign="top">
		Kontakt e-mail:</td><td>
		<?

		while($r450 = mysql_fetch_row($q450)) {
			?>
			<?
			if ($r450[2] == 0) {
				?>
				<input type="text" name="email<?=$r450[0]?>" value="<?=$r450[1]?>" class="default">
				<input type="submit" name="izmijeni_email<?=$r450[0]?>" value=" Izmijeni "> <input type="submit" name="obrisi_email<?=$r450[0]?>" value=" Obriši ">
				<?
			} else {
				print "<b>".$r450[1]."</b>";
			}
			print "<br>\n";
		}
		?>

		<input type="text" name="email_novi" class="default"> <input type="submit" name="dodaj_email" value=" Dodaj e-mail ">
	</td></tr>
	<tr><td colspan="2">Ovim putem ne možete promijeniti vašu <?=$conf_skr_naziv_institucije?> e-mail adresu! Možete postaviti neku drugu adresu (Gmail, Hotmail...) na koju želite da primate obavještenja pored vaše <?=$conf_skr_naziv_institucije?> adrese.</td></tr>


	<tr><td colspan="2">&nbsp;</td></tr>
	<tr><td colspan="2" bgcolor="#999999"><font color="#FFFFFF">LIČNI PODACI:</font></td></tr>
	<tr><td colspan="2"><?=$tekst_licni_podaci?></td></tr>
	<tr><td>
		Djevojačko prezime:</td><td><input type="text" name="djevojacko_prezime" value="<?=mysql_result($q400,0,20)?>" class="default">
	</td></tr>
	<tr><td>
		Ime oca:</td><td><input type="text" name="imeoca" value="<?=mysql_result($q400,0,12)?>" class="default">
	</td></tr>
	<tr><td>
		Prezime oca:</td><td><input type="text" name="prezimeoca" value="<?=mysql_result($q400,0,13)?>" class="default">
	</td></tr>
	<tr><td>
		Ime majke:</td><td><input type="text" name="imemajke" value="<?=mysql_result($q400,0,14)?>" class="default">
	</td></tr>
	<tr><td>
		Prezime majke:</td><td><input type="text" name="prezimemajke" value="<?=mysql_result($q400,0,15)?>" class="default">
	</td></tr>
	<tr><td>
		Datum rođenja:<br/>
		(D.M.G)</td><td><input type="text" name="datum_rodjenja" value="<?
		if (mysql_result($q400,0,4)) print date("d. m. Y.", mysql_result($q400,0,3))?>" class="default">
	</td></tr>
	<tr><td>
		Mjesto rođenja:</td><td>
		<input type="text" name="mjesto_rodjenja" id="mjesto_rodjenja" value="<?=$mjestorvalue?>" class="default" onKeyPress="return comboBoxEdit(event, 'mjesto_rodjenja')" autocomplete="off" onBlur="comboBoxHide('mjesto_rodjenja')"><img src="images/cb_up.png" width="19" height="18" onClick="comboBoxShowHide('mjesto_rodjenja')" id="comboBoxImg_mjesto_rodjenja" valign="bottom"> <img src="images/cb_down.png" style="visibility:hidden">
		<!-- Rezultati pretrage primaoca -->
		<div id="comboBoxDiv_mjesto_rodjenja" style="position:absolute;visibility:hidden">
			<select name="comboBoxMenu_mjesto_rodjenja" id="comboBoxMenu_mjesto_rodjenja" size="10" onClick="comboBoxOptionSelected('mjesto_rodjenja')" onFocus="this.focused=true;" onBlur="this.focused=false;"><?=$gradovir?></select>
		</div>
	</td></tr>
	<tr><td>
		Općina rođenja:</td><td><select name="opcina_rodjenja" class="default"><?=$opciner?></select>
	</td></tr>
	<tr><td>
		Država rođenja:</td><td><select name="drzava_rodjenja" class="default"><?=$drzaver?></select>
	</td></tr>
	<tr><td>
		Nacionalnost:</td><td><select name="nacionalnost" class="default"><?=$nacion?></select>
	</td></tr>
	<tr><td>
		Kanton / regija:</td><td><?=db_dropdown("kanton",mysql_result($q400,0,10), "--Izaberite kanton--") ?> <br/>
	</td></tr>
	<tr><td>
		Državljanstvo:</td><td><select name="drzavljanstvo" class="default"><?=$drzavlj?></select>
	</td></tr>
	<tr><td colspan="2">
		<input type="checkbox" name="borac" <?=$boracke?>> Dijete šehida / borca / pripadnik RVI
	</td></tr>


	<? if ($user_student && false) { ?>
	<tr><td colspan="2">&nbsp;</td></tr>
	<tr><td colspan="2" bgcolor="#999999"><font color="#FFFFFF">PODACI O PRETHODNOM CIKLUSU STUDIJA:</font></td></tr>
	<tr><td>
		Završena srednja škola:</td><td><input type="text" name="srednja_skola" value="<?=mysql_result($q400,0,12)?>" class="default">
	</td></tr>
	<? } ?>


	</table>

	<input type="hidden" name="subakcija" value="potvrda">
	<input type="Submit" value=" Pošalji zahtjev "></form>

	<p>&nbsp;</p>
	<p>Klikom na dugme iznad biće poslan zahtjev koji službe <?=$conf_skr_naziv_institucije_genitiv?> trebaju da provjere i potvrde. Ovo može potrajati nekoliko dana. Molimo da budete strpljivi.</p>
