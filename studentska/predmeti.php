<?

// STUDENTSKA/PREDMETI - administracija predmeta, studentska služba

// v3.9.1.0 (2008/02/19) + Preimenovan bivsi admin_nihada
// v3.9.1.1 (2008/03/04) + Dodajemo upis svih studenata sa studija
// v3.9.1.2 (2008/03/25) + Nova auth tabela
// v3.9.1.3 (2008/04/09) + Nije radila izmjena imena predmeta



function studentska_predmeti() {

global $userid,$user_siteadmin,$user_studentska;

global $_lv_; // Potrebno za genform() iz libvedran


require("lib/manip.php"); // radi ispisa studenata sa predmeta


// Provjera privilegija

if (!$user_studentska && !$user_siteadmin) {
	zamgerlog("nije studentska",3); // 3: error
	biguglyerror("Pristup nije dozvoljen.");
	return;
}



?>
<p><h3>Studentska služba - Predmeti</h3></p>

<?

$akcija = $_REQUEST['akcija'];


if ($akcija == "ogranicenja") {
	$nastavnik = intval($_REQUEST['nastavnik']);
	$predmet = intval($_REQUEST['predmet']);

	// Imena stvari
	$q370 = myquery("select ime,prezime from auth where id=$nastavnik");
	if (mysql_num_rows($q370)<1) {
		zamgerlog("nepoznat nastavnik u$nastavnik",3);
		niceerror("Nepoznat nastavnik");
		return;
	}
	$ime = mysql_result($q370,0,0);
	$prezime = mysql_result($q370,0,1);
	$q371 = myquery("select p.naziv from predmet as p, ponudakursa as pk where pk.id=$predmet and pk.predmet=p.id");
	if (mysql_num_rows($q371)<1) {
		zamgerlog("nepoznat predmet p$predmet",3);
		niceerror("Nepoznat predmet");
		return;
	}
	$naziv_predmeta = mysql_result($q371,0,0);

	?><ul><p>
	<b>Ograničenja za nastavnika <?=$ime." ".$prezime?> na predmetu <?=$naziv_predmeta?></b></p><?

	// Subakcija
	if ($_REQUEST['subakcija']=="izmjena") {
		// Provjera podataka...
		$q374 = myquery("select id from labgrupa where predmet=$predmet");
		$izabrane=0; $grupe=0; $upit="";
		while ($r374 = mysql_fetch_row($q374)) {
			if ($_REQUEST['lg'.$r374[0]]) {
				$izabrane++;
				if ($upit)
					$upit .= ",($nastavnik,$r374[0])";
				else
					$upit = "($nastavnik,$r374[0])";
			}
			$grupe++;
		}
		if ($upit == "") {
			zamgerlog("pokusao ograniciti sve grupe nastavniku u$nastavnik, predmet p$predmet",3);
			niceerror("Nastavnik mora imati pristup barem jednoj grupi");
			print "<br/>Ako ne želite da ima pristup, odjavite ga/je sa predmeta.";
		} else if ($izabrane == $grupe) {
			zamgerlog("ukinuta sva ogranicenja nastavniku u$nastavnik, predmet p$predmet",4);
			$q375 = myquery("delete from ogranicenje where nastavnik=$nastavnik");
			print "<br/>Nastavnik više nema ograničenja!<br/>\n";
		} else {
			zamgerlog("izmijenjena ogranicenja nastavniku u$nastavnik, predmet p$predmet",4);
			$q375 = myquery("delete from ogranicenje where nastavnik=$nastavnik");
			$q376 = myquery("insert into ogranicenje values $upit");
			print "<br/>Postavljena nova ograničenja.<br/>\n";
		}
	}

	?>
	<?=genform("POST")?>
	<input type="hidden" name="subakcija" value="izmjena">
	<p>
	<?
	
	$nema_ogranicenja=0;
	$q372 = myquery("select count(*) from ogranicenje as o, labgrupa as l where o.nastavnik=$nastavnik and o.labgrupa=l.id and l.predmet=$predmet");
	if (mysql_result($q372,0,0)<1) $nema_ogranicenja=1;

	$q373 = myquery("select id,naziv from labgrupa where predmet=$predmet");
	while ($r373 = mysql_fetch_row($q373)) {
		$dodaj="CHECKED";
		if ($nema_ogranicenja==0) {
			$q374=myquery("select count(*) from ogranicenje where labgrupa=$r373[0] and nastavnik=$nastavnik");
			if (mysql_result($q374,0,0)==0) $dodaj="";
		}
		?><input type="checkbox" name="lg<?=$r373[0]?>" <?=$dodaj?>> <?=$r373[1]?><br/><?
	}
	?><br/><input type="submit" value=" Izmijeni "> <input type="button" value=" Nazad " onclick="location.href='?sta=studentska/predmeti&akcija=edit&predmet=<?=$predmet?>';"></form><?
	
}


else if ($akcija == "novi") {
	$naziv = substr(my_escape($_POST['naziv']), 0, 100);
	if (!preg_match("/\w/", $naziv)) {
		zamgerlog("naziv nije ispravan ($naziv)",3);
		niceerror("Naziv nije ispravan");
		return;
	}

	$q390 = myquery("select id from akademska_godina order by naziv desc limit 1");
	if (mysql_num_rows($q390)<1) {
		niceerror("Nije definisana nijedna akademska godina. Molimo kontaktirajte administratora sajta.");
		zamgerlog("ne postoji nijedna akademska godina",3);
		return;
	}
	$ak_god = mysql_result($q390,0,0);
	$q391 = myquery("select id from predmet where naziv='$naziv'");
	if (mysql_num_rows($q391)>0) {
		$pid = mysql_result($q391,0,0);
		$q392 = myquery("select id from ponudakursa where predmet=$pid and akademska_godina=$ak_god");
		if (mysql_num_rows($q392)>0) {
			zamgerlog("predmet vec postoji u ovoj ak.god ($naziv)",3);
			niceerror("Predmet već postoji");
			return;
		}
		print "Predmet već postoji - dodajem ga u izabranu akademsku godinu.<br/><br/>";
		zamgerlog("dodajem predmet p$pid u akademsku godinu ag$ak_god",4);
	} else {
		print "Novi predmet.<br/><br/>";
		$q393 = myquery("insert into predmet set naziv='$naziv'");
		$q391 = myquery("select id from predmet where naziv='$naziv'");
		$pid = mysql_result($q391,0,0);
		zamgerlog("potpuno novi predmet p$pid, akademska godina ag$ak_god",4);
	}

	$q395 = myquery("insert into ponudakursa set predmet=$pid, akademska_godina=$ak_god");
	$q396 = myquery("select id from ponudakursa where predmet=$pid and akademska_godina=$ak_god");
	$predmet = mysql_result($q396,0,0);

	?>
	<script language="JavaScript">
	location.href='<?=genuri()?>&akcija=edit&predmet=<?=$predmet?>';
	</script>
	<?
}


else if ($akcija == "edit") {
	$predmet = intval($_REQUEST['predmet']);

	$oag = intval($_REQUEST['old_akademska_godina']);
	print "<a href=\"?sta=studentska/predmeti&_lv_column_akademska_godina=$oag&search=".$_REQUEST['search']."&offset=".intval($_REQUEST['offset'])."\">Nazad na rezultate pretrage</a><br/><br/>";

	// Izvjestaji

	?>
	<center>
	<table width="700" border="0" cellspacing="0" cellpadding="0"><tr><td width="100" valign="top">
		<table width="100%" border="1" cellspacing="0" cellpadding="0">
			<tr><td bgcolor="#777777" align="center">
				<font color="white"><b>IZVJEŠTAJI:</b></font>
			</td></tr>
			<tr><td align="center"><a href="?sta=izvjestaj/grupe&predmet=<?=$predmet?>">
			<img src="images/32x32/izvjestaj.png" border="0"><br/>Spisak grupa</a></td></tr>
			<tr><td align="center"><a href="?sta=izvjestaj/predmet&predmet=<?=$predmet?>">
			<img src="images/32x32/izvjestaj.png" border="0"><br/>Puni izvještaj</a></td></tr>
			<tr><td align="left">Ispiti:<br/><?
			$q359 = myquery("select i.id,i.naziv,UNIX_TIMESTAMP(i.datum), k.gui_naziv from ispit as i, komponenta as k where i.predmet=$predmet and i.komponenta=k.id order by i.datum,i.komponenta");
			while ($r359 = mysql_fetch_row($q359)) {
				print '* <a href="?sta=izvjestaj/ispit&predmet='.$predmet.'&ispit='.$r359[0].'">'.$r359[3].'<br/> ('.date("d. m. Y.",$r359[2]).')</a><br/>'."\n";
			}
			if (mysql_num_rows($q359)>0)
				print '<br/><a href="?sta=izvjestaj/ispit&predmet='.$predmet.'&ispit=svi">Ukupna statistika</a>';

			?></td></tr>
		</table>
	</td><td width="10" valign="top">&nbsp;
	</td><td width="590" valign="top">
	<?


	// Podaci potrebni u kasnijim upitima

	// Naziv studija
	$q348 = myquery("select s.naziv, pk.semestar, pk.akademska_godina, pk.predmet, pk.studij from studij as s, ponudakursa as pk where pk.id=$predmet and pk.studij=s.id");
	$nazivstudija = mysql_result($q348,0,0).", ".mysql_result($q348,0,1).". semestar";
	$semestar = mysql_result($q348,0,1);
	$akademskagodina=mysql_result($q348,0,2);
	$metapredmet=mysql_result($q348,0,3);
	$studij=mysql_result($q348,0,4);

	// Isti predmet od prosle godine
	$q349 = myquery("select pk.id from ponudakursa as pk where pk.predmet=$metapredmet and pk.studij=$studij and pk.akademska_godina=".($akademskagodina-1));
	if (mysql_num_rows($q349)>0)
		$proslagodina=mysql_result($q349,0,0);
	else
		$proslagodina=0;


	// Submit akcije

	if ($_POST['subakcija'] == "dodaj") {
		$nastavnik = intval($_POST['_lv_column_auth']);
		if ($nastavnik>0) {
			$q360 = myquery("select count(*) from nastavnik_predmet where nastavnik=$nastavnik and predmet=$predmet");
			if (mysql_result($q360,0,0) < 1) {
				$q361 = myquery("insert into nastavnik_predmet set nastavnik=$nastavnik, predmet=$predmet");
			}
			zamgerlog("nastavnik u$nastavnik dodan na predmet p$predmet",4);
		}
	}
	else if ($_GET['subakcija'] == "set_admin") {
		$nastavnik = intval($_GET['nastavnik']);

		$yesno = intval($_GET['yesno']);
		$q362 = myquery("update nastavnik_predmet set admin=$yesno where nastavnik=$nastavnik and predmet=$predmet");
		zamgerlog("nastavnik u$nastavnik proglasen za admina predmeta p$predmet",4);
	}
	else if ($_GET['subakcija'] == "izbaci") {
		$nastavnik = intval($_GET['nastavnik']);
		$q363 = myquery("delete from nastavnik_predmet where nastavnik=$nastavnik and predmet=$predmet");
		zamgerlog("nastavnik u$nastavnik izbacen sa predmeta p$predmet",4);
	}
	else if($_POST['subakcija'] == "podaci") {
		$naziv = my_escape($_POST['naziv']);
		$pid = intval($_POST['pid']);
		$q364 = myquery("update predmet set naziv='$naziv' where id=$pid");
		$q364a = myquery("select id from ponudakursa where predmet=$pid order by akademska_godina desc limit 1");
		$pkid = mysql_result($q364a,0,0);
		zamgerlog("izmijenjeni podaci za predmet p$pkid",4);
	}
	else if($_GET['subakcija'] == "svisastudija") {
		$q365 = myquery("insert into student_predmet select ss.student, $predmet from student_studij as ss where ss.studij=$studij and ss.semestar=$semestar and ss.akademska_godina=$akademskagodina and (select count(*) from student_predmet as sp where sp.predmet=$predmet and sp.student=ss.student)=0");
		nicemessage("Svi studenti sa semestra upisani na predmet.");
		zamgerlog("upisani svi sa semestra na predmet p$predmet",4);
	}
	else if($_GET['subakcija'] == "ispisiponovce") {
		$q366 = myquery("select sp.student from student_predmet as sp, konacna_ocjena as ko where sp.predmet=$predmet and sp.student=ko.student and ko.predmet=$proslagodina and ko.ocjena>=6");
		while ($r366 = mysql_fetch_row($q366))
			ispis_studenta_sa_predmeta($r366[0],$predmet);
		nicemessage("Svi ponovci koji su položili predmet prošle godine ispisani sa predmeta.");
		zamgerlog("ispisani ponovci koji su polozili predmet p$predmet",4);
	}
	else if($_GET['subakcija'] == "prenijeli") {
		$q367 = myquery("insert into student_predmet select ss.student, $predmet from student_studij as ss where ss.studij=$studij and ss.semestar=".($semestar+2)." and (select count(*) from konacna_ocjena as ko where ko.student=ss.student and ko.predmet=$proslagodina and ko.ocjena>=6)=0 and (select count(*) from student_predmet as sp where sp.student=ss.student and sp.predmet=$predmet)=0");
		nicemessage("Svi studenti sa sljedeće godine koji nisu položili predmet upisani na predmet.");
		zamgerlog("upisani svi koji su prenijeli predmet p$predmet",4);
	}


	// Osnovni podaci

	$q350 = myquery("select p.id,p.naziv from predmet as p, ponudakursa as pk where pk.id=$predmet and pk.predmet=p.id");
	if (!($r350 = mysql_fetch_row($q350))) {
		zamgerlog("nepostojeci predmet $predmet",3);
		niceerror("Nepostojeći predmet!");
		return;
	}
	?>
	<?=genform("POST")?>
	<input type="hidden" name="subakcija" value="podaci">
	<input type="hidden" name="pid" value="<?=$r350[0]?>">
	Naziv predmeta<br/> <input type="text" size="40" name="naziv" value="<?=$r350[1]?>"> <input type="submit" value=" Izmijeni ">
	</form><?

	$_lv_["label:aktivan"] = "Predmet je aktivan (vidljiv studentima)";
	$_lv_["label:studij"] = "Program studija";
	$_lv_["where:id"] = "$predmet";
	$_lv_["hidden:predmet"] = 1;
	$_lv_["hidden:motd"] = 1;
	$_lv_["forceedit"]=1;

	print db_form("ponudakursa");


	// Upis studenata na predmet

	// Koliko slusa?
	$q350a = myquery("select count(*) from student_predmet where predmet=$predmet");

	// Koliko sa semestra nije upisano
	$q350b = myquery("select count(*) from student_studij as ss where ss.studij=$studij and ss.semestar=$semestar and ss.akademska_godina=$akademskagodina and (select count(*) from student_predmet as sp where sp.predmet=$predmet and sp.student=ss.student)=0");
	$nijeupisano = mysql_result($q350b,0,0);

	if ($proslagodina>0) {
		// Koliko ponovaca je polozilo predmet
		$q350d = myquery("select count(*) from student_predmet as sp, konacna_ocjena as ko where sp.predmet=$predmet and sp.student=ko.student and ko.predmet=$proslagodina and ko.ocjena>=6");
		$brponovaca = mysql_result($q350d,0,0);

		// Koliko je prenijelo predmet
		$q350e = myquery("select count(*) from student_studij as ss where ss.studij=$studij and ss.semestar=".($semestar+2)." and (select count(*) from konacna_ocjena as ko where ko.student=ss.student and ko.predmet=$proslagodina and ko.ocjena>=6)=0");
		$brprenijetih = mysql_result($q350e,0,0);
	}

	?><p>Na predmet je trenutno upisano <b><?=mysql_result($q350a,0,0)?></b> studenata.<br/>
	<ul><? if ($nijeupisano>0) { ?>Upiši sve studente koji trenutno slušaju studij: <a href="<?=genuri()?>&subakcija=svisastudija"><?=$nazivstudija?> (<?=$nijeupisano?> studenata)</a><br/><? } ?>
	<? if ($proslagodina>0) { ?>Ispiši studente koji su položili predmet: <a href="<?=genuri()?>&subakcija=ispisiponovce"><?=$brponovaca?> studenata</a><br/>
	Upiši sve koji su prenijeli predmet: <a href="<?=genuri()?>&subakcija=prenijeli"><?=$brprenijetih?> studenata</a><? } ?></ul>
	<?


	// Nastavnici na predmetu

	print "<p>Nastavnici angažovani na predmetu:</p>\n";
	$q351 = myquery("select np.nastavnik,np.admin,a.ime,a.prezime from auth as a, nastavnik_predmet as np where np.nastavnik=a.id and np.predmet=$predmet");
	if (mysql_num_rows($q351) < 1) {
		print "<ul><li>Nema nastavnika</li></ul>\n";
	} else {
		?>
		<table width="100%" border="1" cellspacing="0"><tr><td>Ime i prezime</td><td>Administrator predmeta</td><td>Ograničenja</td><td>&nbsp;</td></tr><?
	}
	while ($r351 = mysql_fetch_row($q351)) {
		print '<tr><td><a href="?sta=studentska/nastavnici&akcija=edit&nastavnik='.$r351[0].'">'.$r351[2].' '.$r351[3].'</td>'."\n";

		print '<td><input type="checkbox" onchange="javascript:location.href=\'';
		print genuri()."&subakcija=set_admin&nastavnik=$r351[0]&yesno=";
		if ($r351[1]==1) 
			print "0'\" CHECKED></td>\n"; 
		else 
			print "1'\"></td>\n";

		print '<td><a href="'.genuri().'&akcija=ogranicenja&nastavnik='.$r351[0].'">';
		$q352 = myquery("select l.naziv from ogranicenje as o, labgrupa as l where o.nastavnik=$r351[0] and o.labgrupa=l.id and l.predmet=$predmet");
		if (mysql_num_rows($q352)<1)
			print "Nema";
		while ($r352 = mysql_fetch_row($q352)) {
			print substr($r352[0],0,15).", ";
		}
		print "</a></td>"."\n";

		print "<td><a href=\"".genuri()."&subakcija=izbaci&nastavnik=$r351[0]\">Izbaci</a></td></tr>"."\n";
	}
	if (mysql_num_rows($q351) > 0) {
		print "</table>\n";
	}


	// Dodaj nove nastavnike

	print "<p>Angažman nastavnika na predmetu:\n";
	print genform("POST");
	print '<input type="hidden" name="subakcija" value="dodaj">';
	$_lv_['where:nastavnik']=1;
	print db_dropdown("auth");
	print '<input type="submit" value=" Dodaj "></form></p>';

	?></td></tr></table></center><? // Vanjska tabela

}


else {
	$src = my_escape($_REQUEST["search"]);
	$limit = 20;
	$offset = intval($_REQUEST["offset"]);
	$ak_god = intval($_REQUEST["_lv_column_akademska_godina"]);
	if ($ak_god == 0) {
		$q299 = myquery("select id from akademska_godina order by naziv desc limit 1");
		$ak_god = mysql_result($q299,0,0);
	}

	?>
	<table width="100%" border="0"><tr><td align="left">
		<p><b>Pretraga</b><br/>
		Za prikaz svih predmeta na akademskoj godini, ostavite polje za pretragu prazno.</br>
		<?=genform("POST")?>
		<input type="hidden" name="offset" value="0"> <?/*resetujem offset*/?>
		<?=db_dropdown("akademska_godina",$ak_god, "Sve akademske godine");?><br/>
		<input type="text" size="50" name="search" value="<? if ($src!="") print $src?>"> <input type="Submit" value=" Pretraži "></form>
		<br/>
	<?
	if ($ak_god>0 && $src != "") {
		$q300 = myquery("select count(*) from ponudakursa as pk, predmet as p where pk.akademska_godina=$ak_god and p.naziv like '%$src%' and pk.predmet=p.id");
	} else if ($ak_god>0) {
		$q300 = myquery("select count(*) from ponudakursa where akademska_godina=$ak_god");
	} else if ($src != "") {
		$q300 = myquery("select count(*) from ponudakursa as pk, predmet as p where pk.predmet=p.id and p.naziv like '%$src%'");
	} else {
		$q300 = myquery("select count(*) from ponudakursa");
	}
	$rezultata = mysql_result($q300,0,0);

	if ($rezultata == 0)
		print "Nema rezultata!";
	else {
		if ($rezultata>$limit) {
			print "Prikazujem rezultate ".($offset+1)."-".($offset+20)." od $rezultata. Stranica: ";
	
			for ($i=0; $i<$rezultata; $i+=$limit) {
				$br = intval($i/$limit)+1;
				if ($i==$offset)
					print "<b>$br</b> ";
				else
					print "<a href=\"".genuri()."&offset=$i&_lv_column_akademska_godina=$ak_god\">$br</a> ";
			}
			print "<br/>";
		}
		print "<br/>";

		if ($ak_god>0 && $src != "") {
			$q301 = myquery("select pk.id, p.naziv, ag.naziv, s.kratkinaziv from predmet as p, ponudakursa as pk, akademska_godina as ag, studij as s where pk.akademska_godina=ag.id and ag.id=$ak_god and p.naziv like '%$src%' and pk.predmet=p.id and pk.studij=s.id order by ag.naziv desc, p.naziv limit $offset,$limit");
		} else if ($ak_god>0) {
			$q301 = myquery("select pk.id, p.naziv, ag.naziv, s.kratkinaziv from predmet as p, ponudakursa as pk, akademska_godina as ag, studij as s where pk.akademska_godina=ag.id and ag.id=$ak_god and pk.predmet=p.id and pk.studij=s.id order by ag.naziv desc, p.naziv limit $offset,$limit");
		} else if ($src != "") {
			$q301 = myquery("select pk.id, p.naziv, ag.naziv from predmet as p, ponudakursa as pk, akademska_godina as ag where pk.akademska_godina=ag.id and p.naziv like '%$src%' and pk.predmet=p.id order by ag.naziv desc, p.naziv limit $offset,$limit");
		} else {
			$q301 = myquery("select pk.id, p.naziv, ag.naziv from predmet as p, ponudakursa as pk, akademska_godina as ag where pk.akademska_godina=ag.id and pk.predmet=p.id order by ag.naziv desc,p.naziv limit $offset,$limit");
		}

		print '<table width="100%" border="0">';
		$i=$offset+1;
		while ($r301 = mysql_fetch_row($q301)) {
			if ($ak_god>0)
				print "<tr><td>$i. $r301[1] ($r301[3])</td>\n";
			else
				print "<tr><td>$i. $r301[1] ($r301[2])</td>\n";
			print "<td><a href=\"".genuri()."&old_akademska_godina=$ak_god&akcija=edit&predmet=$r301[0]\">Detalji</a></td>\n";
			print "<td><a href=\"?c=N&sta=nastavnik/predmet&predmet=$r301[0]\">Uređivanje predmeta</a></td></tr>";
			$i++;
		}
		print "</table>";
	}
	?>
		<br/>
		<?=genform("POST")?>
		<input type="hidden" name="akcija" value="novi">
		<b>Novi predmet:</b><br/>
		<input type="text" name="naziv" size="50"> <input type="submit" value=" Dodaj ">
		</form>
	</table>
	<?

}





}

?>
