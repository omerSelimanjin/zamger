<?

// STUDENTSKA/OSOBE - administracija studenata, studentska služba

// v3.9.1.0 (2008/02/19) + Preimenovan bivsi admin_nihada
// v3.9.1.1 (2008/03/21) + Nova auth tabela, popravka upisa na predmet, pojednostavljenje i čišćenje koda
// v3.9.1.2 (2008/04/23) + Trimovanje whitespace-a kod pretrage
// v3.9.1.3 (2008/08/27) + Pretvaram studentska/studenti u studentska/osobe; izbjegnut XSS u linku 'nazad na rezultate pretrage'
// v3.9.1.4 (2008/09/03) + Napravljena akcija 'upis', dodani linkovi na sve vrste upisa u sljedeci semestar; dodano polje aktivan u tabeli auth
// v3.9.1.5 (2008/09/05) + Ispravke bugova; prikazi podatke i za godinu u koju pokusavas upisati studenta
// v3.9.1.6 (2008/09/13) + Upisi studenta u predmete koje je prenio prilikom upisa novog semestra; 
// v3.9.1.7 (2008/09/17) + Dodan debugging ispis; dodaj nastavnike u auth tabelu prilikom kreiranja iz LDAPa; omogucen upis studenta u aktuelnu akademsku godinu ako postoje podaci iz ranijih godina
// v3.9.1.8 (2008/09/19) + Nemoj upisivati studenta u predmete koje je vec polozio
// v3.9.1.9 (2008/10/02) + Ozivljavam dio koda za direktan upis studenta na predmet, radi "kolizije"



// TODO: prva godina studija je hardkodirana u provjeri uslova za upis
// TODO: omoguciti site adminu da proglasi korisnika za studenta, nastavnika itd.
// TODO: popraviti odredjivanje uslova za upis na statusnom ekranu


function studentska_osobe() {

global $userid,$user_siteadmin,$user_studentska;
global $conf_system_auth,$conf_ldap_server,$conf_ldap_domain;

global $_lv_; // Potrebno za genform() iz libvedran


// Provjera privilegija
if (!$user_siteadmin && !$user_studentska) { // 2 = studentska, 3 = admin
	zamgerlog("korisnik nije studentska (admin $admin)",3);
	biguglyerror("Pristup nije dozvoljen.");
	return;
}




?>

<center>
<table border="0"><tr><td>

<?

$akcija = $_REQUEST['akcija'];
$osoba = intval($_REQUEST['osoba']);


// Dodavanje novog korisnika u bazu

if ($akcija == "novi") {
	$ime = substr(my_escape($_POST['ime']), 0, 100);
	if (!preg_match("/\w/", $ime)) {
		zamgerlog("ime nije ispravno ($ime)",3);
		niceerror("Ime nije ispravno");
		return;
	}

	$prezime = substr(my_escape($_POST['prezime']), 0, 100);

	// Probamo tretirati ime kao LDAP UID
	if ($conf_system_auth == "ldap") {
		$uid = $ime;
		$ds = ldap_connect($conf_ldap_server);
		ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
		if ($ds && ldap_bind($ds)) {
			$sr = ldap_search($ds, "", "uid=$uid", array("givenname","sn") );
			$results = ldap_get_entries($ds, $sr);
			if ($results['count'] > 0) {
				$gn = $results[0]['givenname'];
				if (is_array($gn)) $gn = $results[0]['givenname'][0];
				if ($gn) $ime = $gn;

				$sn = $results[0]['sn'];
				if (is_array($sn)) $sn = $results[0]['sn'][0];
				if ($sn) $prezime = $sn;
			} else {
				zamgerlog("korisnik '$uid' nije pronadjen na LDAPu",3);
				$uid = "";
				niceerror("Korisnik nije pronadjen na LDAPu... dodajem novog!");
			}
		} else {
			zamgerlog("ne mogu kontaktirati LDAP server",3);
			niceerror("Ne mogu kontaktirati LDAP server... pravim se da ga nema :(");
		}
	}

	if (!preg_match("/\w/", $prezime)) {
		zamgerlog("prezime nije ispravno ($prezime)",3);
		niceerror("Prezime nije ispravno");
		return;
	}

	// Da li ovaj korisnik već postoji u osoba tabeli?
	$q10 = myquery("select id, ime, prezime from osoba where ime like '$ime' and prezime like '$prezime'");
	if ($r10 = mysql_fetch_row($q10)) {
		zamgerlog("korisnik vec postoji u bazi ('$ime' '$prezime' - ID: $r10[0])",3);
		niceerror("Korisnik već postoji u bazi:");
		print "<br><a href=\"".genuri()."&akcija=edit&nastavnik=$r10[0]\">$r10[1] $r10[2]</a>";
		return;

	} else {
		// Nije u tabeli, dodajemo ga...
		$q30 = myquery("select id from osoba order by id desc limit 1");
		$osoba = mysql_result($q30,0,0)+1;

		$upit = "insert into osoba set id=$osoba, ime='$ime', prezime='$prezime'";

		if ($conf_system_auth == "ldap" && $uid != "") {
			// Ako je LDAP onda imamo email adresu
			$upit = $upit.", email='".$uid.$conf_ldap_domain."'";
			// a ako je student, imamo i brindexa
			if (preg_match("/\w\w(\d\d\d\d\d)/", $uid, $matches))
				$upit = $upit.", brindexa='".$matches[1]."'";

			// Mozemo ga dodati i u auth tabelu
			$q35 = myquery("select count(*) from auth where id=$osoba");
			if (mysql_result($q35,0,0)==0) {
				$q37 = myquery("insert into auth set id=$osoba, login='$uid', admin=1, aktivan=1");
			}
		}

		$q40 = myquery($upit);

		nicemessage("Novi korisnik je dodan.");
		zamgerlog("dodan novi korisnik u$osoba (ID: $osoba)",4); // nivo 4: audit
		$akcija="edit";
		$_POST['subakcija']="";
	}
}



// Izmjena licnih podataka osobe

if ($akcija == "podaci") {

	if ($_REQUEST['subakcija']=="potvrda") {
		$ime = my_escape($_REQUEST['ime']);
		$prezime = my_escape($_REQUEST['prezime']);
		$email = my_escape($_REQUEST['email']);
		$brindexa = my_escape($_REQUEST['brindexa']);
		$mjesto_rodjenja = my_escape($_REQUEST['mjesto_rodjenja']);
		$jmbg = my_escape($_REQUEST['jmbg']);
		$drzavljanstvo = my_escape($_REQUEST['drzavljanstvo']);
		$adresa = my_escape($_REQUEST['adresa']);
		$telefon = my_escape($_REQUEST['telefon']);
		$kanton = intval($_REQUEST['_lv_column_kanton']);

		if (preg_match("/(\d+).*?(\d+).*?(\d+)/", $_REQUEST['datum_rodjenja'], $matches)) {
			$dan=$matches[1]; $mjesec=$matches[2]; $godina=$matches[3];
			if ($godina<100)
				if ($godina<50) $godina+=2000; else $godina+=1900;
			if ($godina<1000)
				if ($godina<900) $godina+=2000; else $godina+=1000;
		}

		$q395 = myquery("update osoba set ime='$ime', prezime='$prezime', email='$email', brindexa='$brindexa', datum_rodjenja='$godina-$mjesec-$dan', mjesto_rodjenja='$mjesto_rodjenja', jmbg='$jmbg', drzavljanstvo='$drzavljanstvo', adresa='$adresa', telefon='$telefon', kanton='$kanton' where id=$osoba");

		zamgerlog("promijenjeni licni podaci korisnika u$osoba",4); // nivo 4 - audit
		?>
		<script language="JavaScript">
		location.href='?sta=studentska/osobe&osoba=<?=$osoba?>&akcija=edit';
		</script>
		<?
		return;
	}

	$q400 = myquery("select ime, prezime, email, brindexa, UNIX_TIMESTAMP(datum_rodjenja), mjesto_rodjenja, jmbg, drzavljanstvo, adresa, telefon, kanton from osoba where id=$osoba");
	if (!($r400 = mysql_fetch_row($q400))) {
		zamgerlog("nepostojeci student u$osoba",3);
		niceerror("Nepostojeći student!");
		return;
	}
	$ime = mysql_result($q400,0,0);
	$prezime = mysql_result($q400,0,1);
	?>

	<h2><?=$ime?> <?=$prezime?> - izmjena ličnih podataka</h2>
	<?=genform("POST")?>
	<table border="0" width="600"><tr><td valign="top">
		Ime: <input type="text" name="ime" value="<?=$ime?>" class="default"><br/>
		Prezime: <input type="text" name="prezime" value="<?=$prezime?>" class="default"><br/>
		Broj indexa (za studente): <input type="text" name="brindexa" value="<?=mysql_result($q400,0,3)?>" class="default"><br/>
		JMBG: <input type="text" name="jmbg" value="<?=mysql_result($q400,0,6)?>" class="default"><br/>
		<br/>
		Datum rođenja: <input type="text" name="datum_rodjenja" value="<?
		if (mysql_result($q400,0,4)) print date("d. m. Y.", mysql_result($q400,0,4))?>" class="default"><br/>
		Mjesto rođenja: <input type="text" name="mjesto_rodjenja" value="<?=mysql_result($q400,0,5)?>" class="default"><br/>
		Državljanstvo: <input type="text" name="drzavljanstvo" value="<?=mysql_result($q400,0,7)?>" class="default"><br/>
		</td><td valign="top">
		Adresa: <input type="text" name="adresa" value="<?=mysql_result($q400,0,8)?>" class="default"><br/>
		Kanton: <?=db_dropdown("kanton",mysql_result($q400,0,10), "--Izaberite kanton--") ?> <br/>
		Telefon: <input type="text" name="telefon" value="<?=mysql_result($q400,0,9)?>" class="default"><br/>
		Kontakt e-mail: <input type="text" name="email" value="<?=mysql_result($q400,0,2)?>" class="default"><br/>
		<br/>
		ID: <b><?=$osoba?></b></td>
	</tr></table>

	<p>
	<input type="hidden" name="subakcija" value="potvrda">
	<input type="Submit" value=" Izmijeni "></form>
	<a href="?sta=studentska/osobe&akcija=edit&osoba=<?=$osoba?>">Povratak nazad</a>
	</p>
	<?

} // if ($akcija == "podaci")




// Upis studenta na semestar

else if ($akcija == "upis") {

	$student = intval($_REQUEST['osoba']);
	$studij = intval($_REQUEST['studij']);
	$semestar = intval($_REQUEST['semestar']);
	$godina = intval($_REQUEST['godina']);

	// Postoji li akademska godina?
	$q495 = myquery("select count(*) from akademska_godina where id=$godina");
	if (mysql_result($q495,0,0)<1) {
		niceerror("Pokušaj upisa u nepostojeću akademsku godinu! Kontaktirajte administratora");
		zamgerlog("pokusaj upisa u nepostojecu akademsku godinu $godina",3); // 3 - error
		return;
	}

	$q500 = myquery("select ime, prezime, brindexa from osoba where id=$osoba");
	$ime = mysql_result($q500,0,0);
	$prezime = mysql_result($q500,0,1);
	$brindexa = intval(mysql_result($q500,0,2));

	?>
	<a href="?sta=studentska/osobe&akcija=edit&osoba=<?=$osoba?>">Nazad na podatke o osobi</a><br/><br/>
	<h2><?=$ime?> <?=$prezime?> - upis</h2><?
	print genform("POST");
	?>
	<input type="hidden" name="subakcija" value="upis_potvrda">
	<?


	// Ako je subakcija, potvrdjujemo da se moze izvrsiti upis
	$ok_izvrsiti_upis=0;

	if ($_REQUEST['subakcija']=="upis_potvrda") {
		$ok_izvrsiti_upis=1;

		$ns = intval($_REQUEST['novi_studij']);
		if ($ns>0) {
			$studij=$ns;
			$_REQUEST['novi_studij'] = 0;
			?>
	<input type="hidden" name="studij" value="<?=$studij?>">
	<input type="hidden" name="novi_studij" value="0">
			<?
		}
	}


	// Izbor studija kod zavrsetka prethodnog - FIXME!
	$q540 = myquery("select zavrsni_semestar, naziv, tipstudija from studij where id=$studij");
	$trajanje=mysql_result($q540,0,0);
	$naziv_studija=mysql_result($q540,0,1);
	$tip_studija=mysql_result($q540,0,2);
	if ($semestar>$trajanje) {
		// Da li je student svojevremeno na prijemnom odabrao drugi studij?
		// TODO!

		$q550 = myquery("select id,naziv from studij where zavrsni_semestar>$semestar and preduslov=$tip_studija");
		?>
		<p><b>Izaberite studij koji će student upisati:</b><br/>
		<?
		while ($r550 = mysql_fetch_row($q550)) {
			print '<input type="radio" name="novi_studij" value="'.$r550[0].'">'.$r550[1]."<br/>\n";
		}
		print "</p>\n\n";
		$ok_izvrsiti_upis=0;
	} else {
		?>
		<p>Upis na studij <?=$naziv_studija?>, <?=$semestar?>. semestar:</p>
		<?
	}



	if ($semestar%2==1) {

	// Da li ima nepoložene predmete sa ranijih semestara?

	// NAPOMENA: studij je po definiciji nepromjenljiv. Ako dođe do promjene u 
	// nastavnom planu i programu, MORA se kreirati novi zapis u tabeli 'studij' jer
	// bi se u suprotnom promjene primijenile retroaktivno sto nije dobro. U praksi,
	// studenti koji upisu fakultet po odredjenom programu, moraju ga zavrsiti po tom
	// programu. Stoga, kao bazu za spisak predmeta na studiju koristimo zadnju
	// akademsku godinu

	$q510 = myquery("select pk.predmet, pk.ects, pk.semestar from ponudakursa as pk, student_predmet as sp where sp.student=$student and sp.predmet=pk.id and pk.semestar<$semestar");
	$ects_pao=0;
	$predmeti_pao=array();
	while ($r510 = mysql_fetch_row($q510)) {
		$q520 = myquery("select count(*) from konacna_ocjena as ko, ponudakursa as pk where ko.student=$student and ko.predmet=pk.id and pk.predmet=$r510[0]");
		if (mysql_result($q520,0,0)<1 && !in_array($r510[0], $predmeti_pao)) { 
			$ects_pao+=$r510[1];
			if ($r510[2]<$semestar-2) $ects_pao += 1000;
			array_push($predmeti_pao, $r510[0]);
		}
	}

	// Ako zavrsava studij, ne smije pasti nijedan
	if ($semestar>$trajanje && count($predmeti_pao)>0) {
		$ects_pao += 1000;
	}

	// Tabela za unos ocjena na predmetima koje je pao:
//	if ($ects_pao>6) {
	if (count($predmeti_pao)>0 && $ok_izvrsiti_upis==0) {
		?>
		<p><b>Predmeti iz kojih je student ostao neocijenjen - upišite eventualne ocjene u polja lijevo:</b></p>
		<table border="0">
		<?
		foreach ($predmeti_pao as $pao) {
			$q530 = myquery("select naziv from predmet where id=$pao");
			?>
			<tr><td><input type="text" size="3" name="pao-<?=$pao?>"></td>
			<td><?=mysql_result($q530,0,0)?></td></tr>
			<?
		}
		?>
		</table>
		<?
	}

	} // if ($semestar%2 ==1)

	// Izborni predmeti
	$q560 = myquery("select p.id, p.naziv, pk.id from predmet as p, ponudakursa as pk where pk.akademska_godina=$godina and pk.studij=$studij and pk.semestar=$semestar and obavezan=0 and pk.predmet=p.id");
	if (mysql_num_rows($q560)>0 && $ns!=0) {
		// student je upravo promijenio studij, mora prvo izabrati izborne predmete
		$ok_izvrsiti_upis=0;
	}
	if (mysql_num_rows($q560)>0 && $ok_izvrsiti_upis==0) {
		?>
		<p><b>Izaberite izborne predmete:</b><br/>
		<?
		while ($r560 = mysql_fetch_row($q560)) {
			$q570 = myquery("select count(*) from konacna_ocjena as ko, ponudakursa as pk where ko.student=$student and ko.predmet=pk.id and pk.predmet=$r560[0]");
			if (mysql_result($q570,0,0)<1) {
				// Nije polozio/la - koristimo pk
				?>
				<input type="checkbox" name="izborni-<?=$r560[2]?>"> <?=$r560[1]?><br/>
				<?
			}
		}
	}


	// Studentu nikada nije zadat broj indexa (npr. prvi put se upisuje)
	if ($brindexa<1 && $ok_izvrsiti_upis==0) {
		?>
		<p><b>Unesite broj indeksa za ovog studenta:</b><br/>
		<input type="text" name="novi_brindexa" size="5"></p>
		<?
	}



	// ------ Izvrsenje upisa!

	if ($ok_izvrsiti_upis==1) {
		// Upis u prvi semestar - kandidat za prijemni postaje student!
		if ($semestar==1) {
			$q640 = myquery("update privilegije set privilegija='student' where osoba=$student and privilegija='prijemni'");

			// AUTH tabelu cemo srediti naknadno
			print "-- $prezime $ime proglašen za studenta<br/>";
		}

		// Novi broj indexa
		$nbri = intval($_REQUEST['novi_brindexa']);
		if ($nbri>0) {
			$q650 = myquery("update osoba set brindexa='$nbri' where id=$student");
			print "-- broj indeksa postavljen na $nbri<br/>";
		}

		// Upisujemo ocjene za predmete koje su dopisane
		foreach ($predmeti_pao as $predmet) {
			$ocjena = intval($_REQUEST["pao-$predmet"]);
			$q579 = myquery("select naziv from predmet where id=$predmet");
			$naziv_predmeta = mysql_result($q579,0,0);
			if ($ocjena>5) {
				// Upisujem dopisanu ocjenu

				$q580 = myquery("select pk.id from ponudakursa as pk, student_predmet as sp where sp.student=$student and sp.predmet=pk.id and pk.predmet=$predmet order by pk.akademska_godina desc limit 1");
				if (mysql_num_rows($q580)<1) {
					niceerror("Nije nikad slušao predmet $predmet!?");
					return;
				}
				$pk = mysql_result($q580,0,0);
				$q590 = myquery("insert into konacna_ocjena set student=$student, predmet=$pk, ocjena=$ocjena");
				print "-- Dopisana ocjena $ocjena za predmet $naziv_predmeta<br/>";
			} else {
				// Student prenio predmet

				$q592 = myquery("select pk.studij,pk.semestar from ponudakursa as pk, student_predmet as sp where sp.student=$student and sp.predmet=pk.id and pk.predmet=$predmet order by pk.akademska_godina desc limit 1");
				$q594 = myquery("select id from ponudakursa where predmet=$predmet and studij=".mysql_result($q592,0,0)." and semestar=".mysql_result($q592,0,1)." and akademska_godina=$godina");

				$q620 = myquery("insert into student_predmet set student=$student, predmet=".mysql_result($q594,0,0));
				print "-- Upisan u predmet $naziv_predmeta koji je prenio s prethodne godine (ako je ovo greška, zapamtite da ga treba ispisati sa predmeta!)<br/>";
			}
		}


		// Upisujemo studenta na novi studij
		$q600 = myquery("insert into student_studij set student=$student, studij=$studij, semestar=$semestar, akademska_godina=$godina");

		// Upisujemo na sve obavezne predmete na studiju
		$q610 = myquery("select pk.id, p.id, p.naziv from ponudakursa as pk, predmet as p where pk.studij=$studij and pk.semestar=$semestar and pk.akademska_godina=$godina and pk.obavezan=1 and pk.predmet=p.id");
		while ($r610 = mysql_fetch_row($q610)) {
			// Da li ga je vec polozio
			$q615 = myquery("select count(*) from konacna_ocjena as ko, ponudakursa as pk where ko.student=$student and ko.predmet=pk.id and pk.predmet=$r610[1]");
			if (mysql_result($q615,0,0)==0) {
				$q620 = myquery("insert into student_predmet set student=$student, predmet=$r610[0]");
				print "-- Student upisan u obavezni predmet $r610[2]<br/>";
			} else {
				print "-- Student NIJE upisan u $r610[2] jer ga je već položio<br/>";
			}
		}

		// Upisujemo na izborne predmete koji su odabrani
		foreach($_REQUEST as $key=>$value) {
			if (substr($key,0,8) != "izborni-") continue;
			if ($value=="") continue;
			$predmet = intval(substr($key,8));
			$q630 = myquery("insert into student_predmet set student=$student, predmet=$predmet");
			$q635 = myquery("select p.naziv from ponudakursa as pk, predmet as p where pk.id=$predmet and pk.predmet=p.id");
			print "-- Student upisan u izborni predmet ".mysql_result($q635,0,0)."<br/>";
		}
		
		nicemessage("Student uspješno upisan na $naziv_studija, $semestar. semestar");
		zamgerlog("Student u$student upisan na studij $studij, semestar $semestar, godina $godina", 4); // 4 - audit
		return;

	} else {
		?>
		<p>&nbsp;</p>
		<input type="submit" value=" Potvrda upisa ">
		</form>
		<?
	}
}



// Pregled informacija o osobi

else if ($akcija == "edit") {
	$pretraga = my_escape($_REQUEST['search']);
	$ofset = my_escape($_REQUEST['offset']);

	?><a href="?sta=studentska/osobe&search=<?=$pretraga?>&offset=<?=$ofset?>">Nazad na rezultate pretrage</a><br/><br/><?
	

	// Submit akcije



	// Promjena korisničkog pristupa i pristupnih podataka
	if ($_REQUEST['subakcija'] == "auth") {

		// LDAP
		if ($conf_system_auth == "ldap") {

			// Ako isključujemo pristup, stavljamo aktivan na 0
			$pristup = intval($_REQUEST['pristup']);
			if ($pristup!=0) {
				$q105 = myquery("update auth set aktivan=0 where id=$osoba");
				zamgerlog("ukinut login za korisnika u$osoba (ldap)",4);
			} else {

			$q107 = myquery("select count(*) from auth where id=$osoba");
			if (mysql_result($q107,0,0)>0) {
				$q105 = myquery("update auth set aktivan=1 where id=$osoba");
				zamgerlog("aktiviran login za korisnika u$osoba (ldap)",4);
			} else {


			// predloženi login
			$suggest_login = gen_ldap_uid($osoba);

			// Tražimo ovaj login na LDAPu...
			$ds = ldap_connect($conf_ldap_server);
			ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
			if (!ldap_bind($ds)) {
				zamgerlog("Ne mogu se spojiti na LDAP server",3); // 3 - greska
				niceerror("Ne mogu se spojiti na LDAP server");
				return;
			}

			$sr = ldap_search($ds, "", "uid=$suggest_login", array() /* just dn */ );
			if (!$sr) {
				zamgerlog("ldap_search() nije uspio.",3);
				niceerror("ldap_search() nije uspio.");
				return;
			}
			$results = ldap_get_entries($ds, $sr);
			if ($results['count'] < 1) {
				zamgerlog("login ne postoji na LDAPu ($suggest_login)",3);
				niceerror("Predloženi login ($suggest_login) nije pronađen na LDAP serveru!");
				print "<p>Da li ste uspravno unijeli broj indeksa, ime i prezime? Ako jeste, kontaktirajte administratora!</p>";

				// Nastavljamo dalje sa edit akcijom kako bi studentska mogla popraviti podatke

			} else {
				// Dodajemo login, ako nije podešen
				$q110 = myquery("select login, aktivan from auth where id=$osoba");
				if (mysql_num_rows($q110)==0) {
					$q111 = myquery("insert into auth set id=$osoba, login='$suggest_login', aktivan=1");
					zamgerlog("kreiran login za korisnika u$osoba (ldap - upis u tabelu)",4);
				}
				else {
					if (mysql_result($q110,0,0) == "") {
						$q112 = myquery("update auth set login='$suggest_login' where id=$osoba");
						zamgerlog("kreiran login za korisnika u$osoba (ldap - postavljeno polje login)",4);
					}
					if (mysql_result($q110,0,1)==0) {
						$q113 = myquery("update auth set aktivan=1 where id=$osoba");
						zamgerlog("kreiran login za korisnika u$osoba (ldap - aktivan=1)",4);
					}
				}

				// Generišemo email adresu ako nije podešena
				$q115 = myquery("select email from osoba where id=$osoba");
				if (mysql_result($q115,0,0) == "") {
					$email = $suggest_login.$conf_ldap_domain;
					$q114 = myquery("update osoba set email='$email' where id=$osoba");
					zamgerlog("promijenjen email za korisnika u$osoba",2); // nivo 2 - edit
				}
			}

			} // if ($q107...) ... else ...
			} // if ($auth!=0) ... else ...

		} // if ($conf_system_auth == "ldap")

		// Lokalna tabela sa šiframa
		else if ($conf_system_auth == "table") {

			$login = my_escape($_REQUEST['login']);
			$password = my_escape($_REQUEST['password']);

			$q120 = myquery("update auth set login='$login', password='$password', aktivan=1 where id=$osoba");
			zamgerlog("dodan/izmijenjen login za korisnika u$osoba (table)",4);

		}
	} // if ($_REQUEST['subakcija'] == "auth")


	// Upis studenta na predmet
	if ($_POST['subakcija'] == "upisi") {
		$predmet = intval($_POST['predmet']);
		$q130 = myquery("select count(*) from student_predmet where student=$student and predmet=$predmet");
		if (mysql_result($q130,0,0)<1) {
			$q135 = myquery("insert into student_predmet set student=$student, predmet=$predmet");
			zamgerlog("student u$student upisan na predmet p$predmet",4);
		}
	}


	// Prijava nastavnika na predmet
	if ($_POST['subakcija'] == "angazuj") {
		$predmet = intval($_POST['predmet']);
		$admin_predmeta = intval($_POST['admin_predmeta']);

		$q120 = myquery("select count(*) from nastavnik_predmet where nastavnik=$osoba and predmet=$predmet");
		if (mysql_result($q120)>0) {
			$q130 = myquery("update nastavnik_predmet set admin=$admin_predmeta where nastavnik=$osoba, predmet=$predmet");
		} else {
			$q140 = myquery("insert into nastavnik_predmet set nastavnik=$osoba, predmet=$predmet, admin=$admin_predmeta");
		}

		zamgerlog("nastavnik u$osoba prijavljen na predmet p$predmet (admin: $admin_predmeta)",4);
	}


	// Osnovni podaci

	$q200 = myquery("select ime, prezime, email, brindexa, UNIX_TIMESTAMP(datum_rodjenja), mjesto_rodjenja, jmbg, drzavljanstvo, adresa, telefon, kanton from osoba where id=$osoba");
	if (!($r200 = mysql_fetch_row($q200))) {
		zamgerlog("nepostojeci student u$osoba",3);
		niceerror("Nepostojeći student!");
		return;
	}
	$ime = mysql_result($q200,0,0);
	$prezime = mysql_result($q200,0,1);
	?>

	<h2><?=$ime?> <?=$prezime?></h2>
	<table border="0" width="600"><tr><td valign="top">
		Ime: <b><?=$ime?></b><br/>
		Prezime: <b><?=$prezime?></b><br/>
		Broj indexa (za studente): <b><?=mysql_result($q200,0,3)?></b><br/>
		JMBG: <b><?=mysql_result($q200,0,6)?></b><br/>
		<br/>
		Datum rođenja: <b><?
		if (mysql_result($q200,0,4)) print date("d. m. Y.", mysql_result($q200,0,4))?></b><br/>
		Mjesto rođenja: <b><?=mysql_result($q200,0,5)?></b><br/>
		Državljanstvo: <b><?=mysql_result($q200,0,7)?></b><br/>
		</td><td valign="top">
		Adresa: <b><?=mysql_result($q200,0,8)?></b><br/>
		Kanton: <b><?
		$kanton=mysql_result($q200,0,10);
		if ($kanton>0) {
			$q205 = myquery("select naziv from kanton where id=$kanton");
			print mysql_result($q205,0,0);
		}
		?></b><br/>
		Telefon: <b><?=mysql_result($q200,0,9)?></b><br/>
		Kontakt e-mail: <b><?=mysql_result($q200,0,2)?></b><br/>
		<br/>
		ID: <b><?=$osoba?></b><br/>
		<br/>
		</form>
		<?=genform("GET")?>
		<input type="hidden" name="akcija" value="podaci">
		<input type="Submit" value=" Izmijeni "></form></td>
	</tr></table>
	<?


	// Login&password

	$q201 = myquery("select login,password,aktivan from auth where id=$osoba");
	if (mysql_num_rows($q201)>0) {
		$login=mysql_result($q201,0,0);
		$password=mysql_result($q201,0,1);
		$pristup=mysql_result($q201,0,2);
	} else $pristup=0;

	if ($conf_system_auth == "table") {
		?>
		<?=genform("POST")?>
		<input type="hidden" name="subakcija" value="auth">
		<table border="0">
		<tr>
			<td colspan="2">Korisnički pristup: <? if(!$pristup) print '<font color="red">NEMA</font>'; ?></td>
			<td>Korisničko ime:<br/> <input type="text" size="10" name="login" value="<?=$login?>"></td>
			<td>Šifra:<br/> <input type="password" size="10" name="password" value="<?=$password?>"></td>
			<td><input type="Submit" value="<? if($pristup) print ' Izmijeni '; else print ' Dodaj '?>"></td>
		</tr></table></form>
		<?
	}

	else if ($conf_system_auth == "ldap") {
		?>
		<table border="0">
		<tr>
			<td colspan="5">Korisnički pristup: <input type="checkbox" name="ima_auth" onchange="javascript:location.href='<?=genuri()?>&subakcija=auth&pristup=<?=$pristup?>';" <? if ($pristup==1) print "CHECKED"; ?>></td>
		</tr></table></form>
		<?
	}

	// Uloge korisnika

	$korisnik_student=$korisnik_nastavnik=$korisnik_prijemni=0;
	print "<p>Tip korisnika: ";
	$q209 = myquery("select privilegija from privilegije where osoba=$osoba");
	while ($r209 = mysql_fetch_row($q209)) {
		if ($r209[0]=="student") {
			print "<b>student,</b> ";
			$korisnik_student=1;
		}
		if ($r209[0]=="nastavnik") {
			print "<b>nastavnik,</b> ";
			$korisnik_nastavnik=1;
		}
		if ($r209[0]=="prijemni") {
			print "<b>kandidat na prijemnom ispitu,</b> ";
			$korisnik_prijemni=1;
		}
		if ($r209[0]=="studentska") {
			print "<b>uposlenik studentske službe,</b> ";
		}
		if ($r209[0]=="siteadmin") {
			print "<b>administrator,</b> ";
		}
	}
	print "</p>\n";

	// Prvo odredjujemo aktuelnu akademsku godinu - ovaj upit se dosta koristi kasnije
	$q210 = myquery("select id,naziv from akademska_godina where aktuelna=1 order by id desc");
	$id_ak_god = mysql_result($q210,0,0);
	$naziv_ak_god = mysql_result($q210,0,1);


	// STUDENT

	if ($korisnik_student) {
		?>
		<hr>
		<h3>STUDENT</h3>
		<?

		// Trenutno upisan na semestar:
		$q220 = myquery("select s.naziv,ss.semestar,ss.akademska_godina,ag.naziv, s.id, s.zavrsni_semestar from student_studij as ss, studij as s, akademska_godina as ag where ss.student=$osoba and ss.studij=s.id and ag.id=ss.akademska_godina order by ag.naziv desc");
		$studij="0";
		$studij_id=$semestar=0;
		$puta=1;

		// Da li je ikada slusao nesto?
		$ikad_studij=$ikad_studij_id=$ikad_semestar=$ikad_ak_god=0;
	
		while ($r220=mysql_fetch_row($q220)) {
			if ($r220[2]==$id_ak_god && $r220[1]>$semestar) { //trenutna akademska godina
				$studij=$r220[0];
				$semestar = $r220[1];
				$studij_id=$r220[4];
				$studij_trajanje=$r220[5];
			}
			else if ($r220[0]==$studij && $r220[1]==$semestar) { // ponovljeni semestri
				$puta++;
			} else if ($r220[1]>$ikad_semestar) {
				$ikad_studij=$r220[0];
				$ikad_semestar=$r220[1];
				$ikad_ak_god=$r220[2];
				$ikad_ak_god_naziv=$r220[3];
				$ikad_studij_id=$r220[4];
			}
		}


		// Izvjestaji
		
		?>
		<div style="float:left; margin-right:10px">
			<table width="100" border="1" cellspacing="0" cellpadding="0">
				<tr><td bgcolor="#777777" align="center">
					<font color="white"><b>IZVJEŠTAJI:</b></font>
				</td></tr>
				<tr><td align="center"><a href="?sta=izvjestaj/index&student=<?=$osoba?>">
				<img src="images/32x32/izvjestaj.png" border="0"><br/>Indeks</a></td></tr>
				<tr><td align="center"><a href="?sta=izvjestaj/progress&student=<?=$osoba?>&razdvoji_ispite=0">
				<img src="images/32x32/izvjestaj.png" border="0"><br/>Bodovi</a></td></tr>
				<tr><td align="center"><a href="?sta=izvjestaj/progress&student=<?=$osoba?>&razdvoji_ispite=1">
				<img src="images/32x32/izvjestaj.png" border="0"><br/>Bodovi + nepoloženi ispiti</a></td></tr>
			</table>
		</div>
		<?


		// Trenutno slusa studij 

		$nova_ak_god=0;

		print "<p align=\"left\">Trenutno (<b>$naziv_ak_god</b>) upisan/a na:<br/>\n";
		if ($studij=="0") {
			print "Nije upisan/a niti u jedan semestar!</p>";

			// Proglasavamo zadnju akademsku godinu koju je slusao za tekucu
			// a tekucu za novu
			if ($ikad_semestar != 0) {
				$nova_ak_god = $id_ak_god;
				$naziv_nove_ak_god = $naziv_ak_god;
				$id_ak_god = $ikad_ak_god;
				$naziv_ak_god = $ikad_ak_god_naziv;
				// Zelimo da se provjeri ECTS:
				$studij = $ikad_studij;
				$studij_id = $ikad_studij_id;
				$semestar = $ikad_semestar;
			}

		} else {
			print "<b>&quot;$studij&quot;</b>, $semestar. semestar ($puta. put)</p>";
			$q230 = myquery("select id, naziv from akademska_godina where id=$id_ak_god+1");
			if (mysql_num_rows($q230)>0) {
				$nova_ak_god = mysql_result($q230,0,0);
				$naziv_nove_ak_god = mysql_result($q230,0,1);
			}
		}


		if ($nova_ak_god!=0) {

		// Ne prikazuj podatke o upisu dok se ne kreira nova ak. godina
		?><p>Upis u akademsku <b><?=$naziv_nove_ak_god?></b> godinu:</p><?


		// Da li je vec upisan?
		$q235 = myquery("select s.naziv from student_studij as ss, studij as s where ss.student=$osoba and ss.studij=s.id and ss.akademska_godina=$nova_ak_god");
		if (mysql_num_rows($q235)>0) {
			?><p>Student je upisan na studij: <b><?=mysql_result($q235,0,0)?></b></p><?
		} else {


		// Ima li uslove za upis
		if ($semestar==0 && $ikad_semestar==0) {
			?><p>Nemamo podataka da je ovaj student ikada bio upisan na fakultet.</p><?

		} else if ($studij=="0") {
			if ($ikad_semestar%2==0) $ikad_semestar--;
			// Trenutno nije upisan na fakultet, ali upisacemo ga
			?><p><a href="?sta=studentska/osobe&osoba=<?=$osoba?>&akcija=upis&studij=<?=$ikad_studij_id?>&semestar=<?=$ikad_semestar?>&godina=<?=$nova_ak_god?>">Ponovo upiši studenta na <?=$ikad_studij?>, <?=$ikad_semestar?>. semestar.</a></p>
			<?

		} else if ($semestar%2!=0) {
			// S neparnog na parni ide automatski
			?><p>Student je stekao uslove za upis na &quot;<?=$studij?>&quot;, <?=($semestar+1)?> semestar</p>
			<p><a href="?sta=studentska/osobe&osoba=$osoba&akcija=upis&studij=<?=$studij_id?>&semestar=<?=($semestar+1)?>&godina=<?=$nova_ak_god?>">Upiši studenta na &quot;<?=$studij?>&quot;, <?=($semestar+1)?> semestar.</a></p>
			<?

		} else {
			// Sumiramo ECTS bodove
			$suma_ects=0;
			$pao="";
			$q240 = myquery("select pk.id,p.id,p.naziv,pk.ects from predmet as p, ponudakursa as pk where pk.predmet=p.id and pk.studij=$studij_id and pk.akademska_godina=$id_ak_god and (pk.semestar=$semestar or pk.semestar=".($semestar-1).")");
			while ($r240 = mysql_fetch_row($q240)) {
				$q250 = myquery("select count(*) from konacna_ocjena as ko, ponudakursa as pk where ko.student=$osoba and ko.predmet=pk.id and pk.predmet=$r240[1]");
				if (mysql_result($q250,0,0)>0) {
					$suma_ects += $r240[3];
				}
			}

			// Provjeravamo nepoložene ispite sa prve godine - FIXME
			if ($semestar>=4) {
				$stara_akgod = $id_ak_god - ($semestar-2)/2;
				$q260 = myquery("select pk.id,p.id,p.naziv from predmet as p, ponudakursa as pk where pk.predmet=p.id and pk.studij=1 and pk.akademska_godina=$stara_akgod"); // 1 = Prva godina studija
				while ($r260 = mysql_fetch_row($q260)) {
					$q270 = myquery("select count(*) from konacna_ocjena as ko, ponudakursa as pk where ko.student=$osoba and ko.predmet=pk.id and pk.predmet=$r260[1]");
					if (mysql_result($q270,0,0)<1) {
						$pao = " - nepoložen predmet sa prve godine studija.";
					}
				}
			}

			// Provjeravamo nepoložene predmete s viših godina!?
			for ($i=4; $i<=$semestar-2; $i+=2) {
				$stara_akgod = $id_ak_god - ($semestar-$i)/2;
				$q280 = myquery("select pk.id,p.id,p.naziv from predmet as p, ponudakursa as pk where pk.predmet=p.id and pk.studij=$studij_id and pk.akademska_godina=$stara_akgod"); // 1 = Prva godina studija
				while ($r280 = mysql_fetch_row($q280)) {
					$q290 = myquery("select count(*) from konacna_ocjena as ko, ponudakursa as pk where ko.student=$osoba and ko.predmet=pk.id and pk.predmet=$r280[1]");
					if (mysql_result($q290,0,0)<1) {
						$pao = " - nepoložen predmet sa ".($i/2).". godine $r280[2]";
					}
				}

			}

			// Koji je sljedeci studij?
			if ($semestar==$studij_trajanje) {
				// POSEBAN SLUCAJ - prva godina studija ETF - FIXME
				if ($studij=="Prva godina studija") { 
					$sta="drugu godinu studija";
					$uslov_ects = 54;
				} else {
					$sta = "sljedeći nivo studija";
					$uslov_ects = 60;
				}
			}
			else {
				$sta = "&quot;$studij&quot;, ".($semestar+1).". semestar";
				$uslov_ects = 60; // Nema prenosenja predmeta na visim godinama
			}

			// Konačan ispis
			if ($suma_ects>=$uslov_ects && $pao=="") {
				?><p>Student je stekao/la uslove za upis na <?=$sta?></p>
				<p><a href="?sta=studentska/osobe&osoba=<?=$osoba?>&akcija=upis&studij=<?=$studij_id?>&semestar=<?=($semestar+1)?>&godina=<?=$nova_ak_god?>">Upiši studenta na <?=$sta?>.</a></p>
				<?
			} else {
				?><p>Student NIJE stekao/la uslove za <?=$sta?><br/>(ukupno skupljeno <?=$suma_ects?> ECTS bodova<?=$pao?>)</p>
				<p><a href="?sta=studentska/osobe&osoba=<?=$osoba?>&akcija=upis&studij=<?=$studij_id?>&semestar=<?=($semestar-1)?>&godina=<?=$nova_ak_god?>">Ponovo upiši studenta na <?=$studij?>, <?=($semestar-1)?>. semestar (<?=($puta+1)?>. put).</a></p>
				<p><a href="?sta=studentska/osobe&osoba=<?=$osoba?>&akcija=upis&studij=<?=$studij_id?>&semestar=<?=($semestar+1)?>&godina=<?=$nova_ak_god?>">Upiši studenta na <?=$sta?>.</a></p>
				<?
			}
		}

		} // if ($q235... else ... -- nije vec upisan nigdje
		} // if (mysql_num_rows($q230  -- da li postoji ak. god. iza aktuelne?

		// Upis studenta na predmet
		?>
		<p>&nbsp;</p>
		<p>Upiši studenta na predmet:<br/>
		<?=genform("POST");?>
		<input type="hidden" name="subakcija" value="upisi">
		<select name="predmet">
		<option>--- Izaberite predmet ---</option>
		<?
		$q300 = myquery("select pk.id, p.naziv, s.kratkinaziv from ponudakursa as pk, predmet as p, studij as s where pk.akademska_godina=$id_ak_god and pk.studij=s.id and pk.predmet=p.id order by p.naziv");
		while ($r300 = mysql_fetch_row($q300)) {
			$q310 = myquery("select count(*) from student_predmet where predmet=$r300[0] and student=$osoba");
			if (mysql_result($q310,0,0)>0) continue;
			print "<option value=\"$r300[0]\">$r300[1] ($r300[2])</option>\n";
		}
		?>
		</select>&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="submit" value=" Upiši ">
		</form>
		<?


		print "\n<div style=\"clear:both\"></div>\n";
	} // STUDENT



	// NASTAVNIK

	if ($korisnik_nastavnik) {
		?>
		<br/><hr>
		<h3>NASTAVNIK</h3>
		<p>Angažovan/a na predmetima (akademska godina <b><?=$naziv_ak_god?></b>):</p>
		<ul>
		<?
		$q180 = myquery("select pk.id, p.naziv, np.admin, s.kratkinaziv from nastavnik_predmet as np, predmet as p, ponudakursa as pk, studij as s where np.nastavnik=$osoba and np.predmet=pk.id and pk.akademska_godina=$id_ak_god and pk.predmet=p.id and pk.studij=s.id");
		if (mysql_num_rows($q180) < 1)
			print "<li>Nijedan</li>\n";
		while ($r180 = mysql_fetch_row($q180)) {
			print "<li><a href=\"?sta=studentska/predmeti&akcija=edit&predmet=$r180[0]\">$r180[1] ($r180[3])</a>";
			if ($r180[2] == 1) print " (Administrator predmeta)";
			print "</li>\n";
		}
		?></ul>
		<p>Za prethodne akademske godine, koristite pretragu na kartici &quot;Predmeti&quot;<br/></p>
	
		<?

		// Angažman na predmetu
	
		?><p>Angažuj nastavnika na:
		<?=genform("POST")?>
		<input type="hidden" name="subakcija" value="angazuj">
		<select name="predmet" class="default"><?
		$q190 = myquery("select pk.id, p.naziv, s.kratkinaziv from predmet as p, ponudakursa as pk, studij as s where pk.predmet=p.id and pk.akademska_godina=$id_ak_god and pk.studij=s.id order by p.naziv");
		while ($r190 = mysql_fetch_row($q190)) {
			print "<option value=\"$r190[0]\">$r190[1] ($r190[2])</a>\n";
		}
		?></select>&nbsp;
		<input type="submit" value=" Dodaj "></form></p>
		<?
	}





	// PRIJEMNI

	if ($korisnik_prijemni) {
		?>
		<br/><hr>
		<h3>KANDIDAT NA PRIJEMNOM ISPITU</h3>
		<p>Ostvareni bodovi:<br/>
		<?
		$q195 = myquery("select opci_uspjeh, kljucni_predmeti, dodatni_bodovi, izasao_na_prijemni, prijemni_ispit, prijavio_drugi, prijemni_ispit_dva from prijemni where id=".($osoba-2000)); // FIXME!
		while ($r195 = mysql_fetch_row($q195)) {
			?>
			<ul>
				<li>Opći uspjeh: <b><?=$r195[0]?></b></li>
				<li>Ključni predmeti: <b><?=$r195[1]?></b></li>
				<li>Dodatni bodovi: <b><?=$r195[2]?></b></li>
			<?
			if ($r195[3]==1) {
				?>
				<li>Prijemni ispit (1. termin): <b><?=$r195[4]?></b></li>
				<?
			}
			if ($r195[5]>0) {
				?>
				<li>Prijemni ispit (2. termin): <b><?=$r195[6]?></b></li>
				<?
			}
		}

		$q198 = myquery("select id, naziv from akademska_godina order by id desc limit 1");

		$id_ak_god = mysql_result($q198,0,0);
		$naziv_ak_god = mysql_result($q198,0,1);


		?>
		</ul></p>

		<p><a href="?sta=studentska/osobe&osoba=<?=$osoba?>&akcija=upis&studij=1&semestar=1&godina=<?=$id_ak_god?>">Upiši studenta u Prvu godinu studija u akademskoj <?=$naziv_ak_god?> godini</a></p>
		<?
		
	}

	?></td></tr></table></center><? // Vanjska tabela

}



// Spisak osoba

else {
	$src = my_escape($_REQUEST["search"]);
	$limit = 20;
	$offset = intval($_REQUEST["offset"]);

	?>
	<p><h3>Studentska služba - Studenti i nastavnici</h3></p>

	<table width="500" border="0"><tr><td align="left">
		<p><b>Pretraži osobe:</b><br/>
		Unesite dio imena i prezimena ili broj indeksa<br/>
		<?=genform("POST")?>
		<input type="hidden" name="offset" value="0"> <?/*resetujem offset*/?>
		<input type="text" size="50" name="search" value="<? if ($src!="sve") print $src?>"> <input type="Submit" value=" Pretraži "></form>
		<a href="<?=genuri()?>&search=sve">Prikaži sve osobe</a><br/><br/>
	<?
	if ($src) {
		if ($src == "sve") {
			$q100 = myquery("select count(*) from osoba");
			$q101 = myquery("select id,ime,prezime,brindexa from osoba order by prezime,ime limit $offset,$limit");
		} else {
			$src = preg_replace("/\s+/"," ",$src);
			$src=trim($src);
			$dijelovi = explode(" ", $src);
			$query = "";
			foreach($dijelovi as $dio) {
				if ($query != "") $query .= "or ";
				$query .= "ime like '%$dio%' or prezime like '%$dio%' or brindexa like '%$dio%' ";
				if (intval($dio)>0) $query .= "or id=".intval($dio)." ";
			}
			$q100 = myquery("select count(*) from osoba where ($query)");
			$q101 = myquery("select id,ime,prezime,brindexa from osoba where ($query) order by prezime,ime limit $offset,$limit");
		}
		$rezultata = mysql_result($q100,0,0);
		if ($rezultata == 0)
			print "Nema rezultata!";
		else if ($rezultata>$limit) {
			print "Prikazujem rezultate ".($offset+1)."-".($offset+20)." od $rezultata. Stranica: ";

			for ($i=0; $i<$rezultata; $i+=$limit) {
				$br = intval($i/$limit)+1;
				if ($i==$offset)
					print "<b>$br</b> ";
				else
					print "<a href=\"".genuri()."&offset=$i\">$br</a> ";
			}
			print "<br/>";
		}
//		else
//			print "$rezultata rezultata:";

		print "<br/>";

		print '<table width="100%" border="0">';
		$i=$offset+1;
		while ($r101 = mysql_fetch_row($q101)) {
			print "<tr ";
			if ($i%2==0) print "bgcolor=\"#EEEEEE\"";
			print "><td>$i. $r101[2] $r101[1]";
			if (intval($r101[3])>0) print " ($r101[3])";
			print "</td><td><a href=\"".genuri()."&akcija=edit&osoba=$r101[0]\">Detalji</a></td></tr>";
			$i++;
		}
		print "</table>";
	}

	?>
		<br/>
		<?=genform("POST")?>
		<input type="hidden" name="akcija" value="novi">
		<b>Unesite novu osobu:</b><br/>
		<table border="0" cellspacing="0" cellpadding="0" width="100%">
		<tr><td>Ime<? if ($conf_system_auth == "ldap") print " ili login"?>:</td><td>Prezime:</td><td>&nbsp;</td></tr>
		<tr>
			<td><input type="text" name="ime" size="15"></td>
			<td><input type="text" name="prezime" size="15"></td>
			<td><input type="submit" value=" Dodaj "></td>
		</tr></table>
		</form>
	<?
	?>

	</td></tr></table>
	<?
}


?>
</td></tr></table></center>
<?


}
