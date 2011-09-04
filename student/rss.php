<?

// RSS - feed za studente

// v3.9.1.0 (2008/04/30) + pocetak
// v3.9.1.1 (2008/10/24) + Popravljen entity u linku za common/inbox
// v4.0.0.0 (2009/02/19) + Release
// v4.0.9.1 (2009/03/31) + Tabela ispit preusmjerena sa ponudakursa na tabelu predmet
// v4.0.9.2 (2009/03/31) + Tabela konacna_ocjena preusmjerena sa ponudakursa na tabelu predmet
// v4.0.9.3 (2009/04/01) + Tabela zadaca preusmjerena sa ponudakursa na tabelu predmet; popravljen link na stranicu za konacnu ocjenu (greska unesena sa r372)
// v4.0.9.4 (2009/04/06) + Dodano polje pubDate na sve kanale
// v4.0.9.5 (2009/04/19) + Popravljen link na rezultate ispita
// v4.0.9.6 (2009/04/29) + Prebacujem tabelu poruka (opseg 5) sa ponudekursa na predmet (neki studenti ce mozda dobiti dvije identicne poruke); jos uvijek koristena auth tabela za ime i prezime, sto spada u davnu historiju zamgera
// v4.0.9.7 (2009/05/01) + Parametri modula student/predmet i student/zadaca su sada predmet i ag


$broj_poruka = 10;


require("lib/libvedran.php");
require("lib/zamger.php");
require("lib/config.php");

dbconnect2($conf_dbhost,$conf_dbuser,$conf_dbpass,$conf_dbdb);


// Pretvaramo rss id u userid
$id = my_escape($_REQUEST['id']);
$q1 = myquery("select auth from rss where id='$id'");
if (mysql_num_rows($q1)<1) {
	print "Greska! Nepoznat RSS ID $id";
	return 0;
}
$userid = mysql_result($q1,0,0);
// Update timestamp
$q2 = myquery("update rss set access=NOW() where id='$id'");


// Ime studenta
$q5 = myquery("select ime,prezime from osoba where id=$userid");
if (mysql_num_rows($q5)<1) {
	print "Greska! Nepoznat userid $userid";
	return 0;
}
$ime = mysql_result($q5,0,0); $prezime = mysql_result($q5,0,1);


header("Content-type: application/rss+xml");

?>
<<?='?'?>xml version="1.0" encoding="utf-8"?>
<!DOCTYPE rss PUBLIC "-//Netscape Communications//DTD RSS 0.91//EN" "http://www.rssboard.org/rss-0.91.dtd">
<rss version="0.91">
<channel>
        <title>Zamger RSS</title>
        <link><?=$conf_site_url?></link>
        <description>Aktuelne informacije za studenta <?=$ime?> <?=$prezime?></description>
        <language>bs-ba</language>
<?



$vrijeme_poruke = array();
$code_poruke = array();

/*$vrijeme_poruke[1]=1;
$code_poruke[1]="<item>
		<title>hello</title>
		<link>$conf_site_url/index.php?sta=student/zadaca&amp;zadaca=$r10[0]&amp;predmet=$r10[4]</link>
		<description><![CDATA[hello hello]]>
	</item>";

print $code_poruke[1];*/

// Rokovi za slanje zadaća

$q10 = myquery("select z.id, z.naziv, UNIX_TIMESTAMP(z.rok), p.naziv, pk.id, UNIX_TIMESTAMP(z.vrijemeobjave), p.id, pk.akademska_godina from zadaca as z, student_predmet as sp, ponudakursa as pk, predmet as p where z.predmet=pk.predmet and z.akademska_godina=pk.akademska_godina and sp.student=$userid and sp.predmet=pk.id and pk.predmet=p.id and z.rok>curdate() and z.aktivna=1 order by rok desc limit $broj_poruka");
while ($r10 = mysql_fetch_row($q10)) {
	// Da li je aktivan modul za zadaće?
	$q12 = myquery("select count(*) from studentski_modul as sm, studentski_modul_predmet as smp where sm.modul='student/zadaca' and sm.id=smp.studentski_modul and smp.predmet=$r10[6] and smp.akademska_godina=$r10[7]");
	if (mysql_result($q12,0,0)==0) continue;

	$code_poruke["z".$r10[0]] = "<item>
		<title>Objavljena zadaća $r10[1], predmet $r10[3]</title>
		<link>$conf_site_url/index.php?sta=student/zadaca&amp;zadaca=$r10[0]&amp;predmet=$r10[6]&amp;ag=$r10[7]</link>
		<description><![CDATA[Rok za slanje je ".date("d. m. Y h:i ",$r10[2]).".]]></description>
	</item>\n";
	$vrijeme_poruke["z".$r10[0]] = $r10[5];
	$poruka="Objavljena zadaća " . $r10[1] . ", predmet ". $r10[3] . ". Rok za slanje je: " . date("d. m. Y h:i ",$r10[2]) . ".";
	$q205 = myquery("select count(id) from notifikacija where tekst=$poruka and student=$userid");
	if (mysql_result($q205,0,0)==0) continue;
	$link=$conf_site_url."/index.php?sta=student/zadaca&amp;zadaca=".$r10[0]."&amp;predmet=".$r10[6]."&amp;ag=".$r10[7];
	$tip=1;
	$procitana=0;
	$vrijeme_poruke=$r10[5];
	$q200 = myquery("insert into notifikacija set tekst=$poruka, link=$link, tip=$tip, procitana=$procitana, vrijeme=$vrijeme_poruke, student=$userid");
}


// Objavljeni rezultati ispita

$q15 = myquery("select i.id, i.predmet, k.gui_naziv, UNIX_TIMESTAMP(i.vrijemeobjave), p.naziv, UNIX_TIMESTAMP(i.datum), pk.id, p.id, pk.akademska_godina from ispit as i, komponenta as k, student_predmet as sp, ponudakursa as pk, predmet as p where sp.student=$userid and sp.predmet=pk.id and i.predmet=pk.predmet and i.akademska_godina=pk.akademska_godina and i.komponenta=k.id and pk.predmet=p.id order by i.vrijemeobjave desc limit $broj_poruka");
while ($r15 = mysql_fetch_row($q15)) {
	if ($r15[3] < time()-60*60*24*30) continue; // preskacemo starije od mjesec dana
	$code_poruke["i".$r15[0]] = "<item>
		<title>Objavljeni rezultati ispita $r15[2] (".date("d. m. Y",$r15[5]).") - predmet $r15[4]</title>
		<link>$conf_site_url/index.php?sta=student/predmet&amp;predmet=$r15[7]&amp;ag=$r15[8]</link>
		<description></description>
	</item>\n";
	$vrijeme_poruke["i".$r15[0]] = $r15[3];
	$poruka="Objavljeni rezultati ispita " . $r15[2] . " " . date("d. m. Y",$r15[5]) . "- predmet ". $r15[4] . ".";
	$q206 = myquery("select count(id) from notifikacija where tekst=$poruka and student=$userid");
	if (mysql_result($q206,0,0)==0) continue;
	$link=$conf_site_url."/index.php?sta=student/predmet&amp;predmet=".$r15[7]."&amp;ag=".$r15[8];
	$tip=1;
	$procitana=0;
	$vrijeme_poruke=$r15[3];
	$q201 = myquery("insert into notifikacija set tekst=$poruka, link=$link, tip=$tip, procitana=$procitana, vrijeme=$vrijeme_poruke, student=$userid");
}



// konacna ocjena

$q17 = myquery("select pk.id, ko.ocjena, UNIX_TIMESTAMP(ko.datum), p.naziv, p.id, pk.akademska_godina from konacna_ocjena as ko, student_predmet as sp, ponudakursa as pk, predmet as p where ko.student=$userid and sp.student=$userid and sp.predmet=pk.id and ko.predmet=pk.predmet and ko.akademska_godina=pk.akademska_godina and pk.predmet=p.id order by ko.datum desc limit $broj_poruka");
while ($r17 = mysql_fetch_row($q17)) {
	if ($r17[2] < time()-60*60*24*30) continue; // preskacemo starije od mjesec dana
	$code_poruke["k".$r17[0]] = "<item>
		<title>Čestitamo! Dobili ste $r17[1] -- predmet $r17[3]</title>
		<link>$conf_site_url/index.php?sta=student/predmet&amp;predmet=$r17[4]&amp;ag=$r17[5]</link>
		<description></description>
	</item>\n";
	$vrijeme_poruke["k".$r17[0]] = $r17[2];
	$poruka="Čestitamo! Dobili ste " . $r17[1] . "- predmet ".  $r17[3] . ".";
	$q207 = myquery("select count(id) from notifikacija where tekst=$poruka and student=$userid");
	if (mysql_result($q207,0,0)==0) continue;
	$link=$conf_site_url."/index.php?sta=student/predmet&amp;predmet=".$r17[4]."&amp;ag=".$r17[5];
	$tip=1;
	$vrijeme_poruke=$r17[2];
	$procitana=0;
	$q202 = myquery("insert into notifikacija set tekst=$poruka, link=$link, tip=$tip, procitana=$procitana, vrijeme=$vrijeme_poruke, student=$userid");
}



// pregledane zadace
// (ok, ovo moze biti JAAAKO sporo ali dacemo sve od sebe da ne bude ;) )

$q18 = myquery("select zk.id, zk.redni_broj, UNIX_TIMESTAMP(zk.vrijeme), p.naziv, z.naziv, pk.id, z.id, p.id, pk.akademska_godina from zadatak as zk, zadaca as z, ponudakursa as pk, predmet as p where zk.student=$userid and zk.status!=1 and zk.status!=4 and zk.zadaca=z.id and z.predmet=p.id and pk.predmet=p.id and pk.akademska_godina=z.akademska_godina order by zk.id desc limit 10");
$zadaca_bila = array();
while ($r18 = mysql_fetch_row($q18)) {
	if (in_array($r18[6],$zadaca_bila)) continue; // ne prijavljujemo vise puta istu zadacu
	if ($r18[2] < time()-60*60*24*30) break; // IDovi bi trebali biti hronoloskim redom, tako da ovdje mozemo prekinuti petlju
	$code_poruke["zp".$r18[0]] = "<item>
		<title>Pregledana zadaća $r18[4], predmet $r18[3]</title>
		<link>$conf_site_url/index.php?sta=student/predmet&amp;predmet=$r18[7]&amp;ag=$r18[8]</link>
		<description><![CDATA[Posljednja izmjena: ".date("d. m. Y. h:i:s",$r18[2])."]]></description>
	</item>\n";
	array_push($zadaca_bila,$r18[6]);
	$vrijeme_poruke["zp".$r18[0]] = $r18[2];
	$poruka="Pregledana zadaća " . $r18[4] . "- predmet ".  $r18[3] . ".";
	$q208 = myquery("select count(id) from notifikacija where tekst=$poruka and student=$userid");
	if (mysql_result($q208,0,0)==0) continue;
	$link=$conf_site_url."/index.php?sta=student/predmet&amp;predmet=".$r18[7]."&amp;ag=".$r18[8];
	$tip=1;
	$procitana=0;
	$vrijeme_poruke=$r18[2];
	$q203 = myquery("insert into notifikacija set tekst=$poruka, link=$link, tip=$tip, procitana=$procitana, vrijeme=$vrijeme_poruke, student=$userid");
}



// PORUKE (izvadak iz inboxa)


// Zadnja akademska godina
$q20 = myquery("select id,naziv from akademska_godina where aktuelna=1 order by id desc limit 1");
$ag = mysql_result($q20,0,0);
$ag_naziv = mysql_result($q20,0,1);

// Studij koji student trenutno sluša
$studij=0;
$q30 = myquery("select studij,semestar from student_studij where student=$userid and akademska_godina=$ag order by semestar desc limit 1");
if (mysql_num_rows($q30)>0) {
	$studij = mysql_result($q30,0,0);
}



$q100 = myquery("select id, UNIX_TIMESTAMP(vrijeme), opseg, primalac, naslov, tip, posiljalac from poruka order by vrijeme desc");
while ($r100 = mysql_fetch_row($q100)) {
	$id = $r100[0];
	$opseg = $r100[2];
	$primalac = $r100[3];
	if ($opseg == 2 || $opseg==3 && $primalac!=$studij || $opseg==4 && $primalac!=$ag ||  $opseg==7 && $primalac!=$userid)
		continue;
	if ($opseg==5) {
		// Poruke od starih akademskih godina nisu relevantne
		if ($r100[1]<mktime(0,0,0,9,1,intval($ag_naziv))) continue;

		// odredjujemo da li student slusa predmet
		$q110 = myquery("select count(*) from student_predmet as sp, ponudakursa as pk where sp.student=$userid and sp.predmet=pk.id and pk.predmet=$primalac and pk.akademska_godina=$ag");
		if (mysql_result($q110,0,0)<1) continue;
	}
	if ($opseg==6) {
		// da li je student u labgrupi?
		$q115 = myquery("select count(*) from student_labgrupa where student=$userid and labgrupa=$primalac");
		if (mysql_result($q115,0,0)<1) continue;
	}
	$vrijeme_poruke[$id]=$r100[1];

	// Fino vrijeme
	$vr = $vrijeme_poruke[$id];
	$vrijeme="";
	if (date("d.m.Y",$vr)==date("d.m.Y")) $vrijeme = "danas ";
	else if (date("d.m.Y",$vr+3600*24)==date("d.m.Y")) $vrijeme = "juče ";
	else $vrijeme .= date("d.m. ",$vr);
	$vrijeme .= date("H:i",$vr);

	$naslov = $r100[4];
	// Ukidam nove redove u potpunosti
	$naslov = str_replace("\n", " ", $naslov);
	// RSS ne podržava &quot; entitet!?
	$naslov = str_replace("&quot;", '"', $naslov);
	if (strlen($naslov)>30) $naslov = substr($naslov,0,28)."...";
	if (!preg_match("/\S/",$naslov)) $naslov = "[Bez naslova]";

	// Posiljalac
	if ($r100[6]==0) {
		$posiljalac="Administrator";
	} else {
		$q120 = myquery("select ime,prezime from osoba where id=$r100[6]");
		if (mysql_num_rows($q120)>0) {
			$posiljalac=mysql_result($q120,0,0)." ".mysql_result($q120,0,1);
		} else {
			$posiljalac="Nepoznat";
		}
	}

	if ($r100[5]==1)
	{
		$title="Obavijest";
		$poruka="Obavijest: " . $naslov . " ".  $vrijeme;
	}
	else
		{
			$title="Poruka";
		$poruka="Poruka: " . $naslov . " ". $vrijeme;
		}

	$code_poruke[$id]="<item>
		<title>$title: $naslov ($vrijeme)</title>
		<link>$conf_site_url/index.php?sta=common%2Finbox&amp;poruka=$id</link>
		<description>Poslao: $posiljalac</description>
	</item>\n";
	
	$q209 = myquery("select count(id) from notifikacija where tekst=$poruka and student=$userid");
	if (mysql_result($q209,0,0)==0) continue;
	$link=$conf_site_url."/index.php?sta=common%2Finbox&amp;poruka=".$id;
	$tip=2;
	$procitana=0;
	$q204 = myquery("insert into notifikacija set tekst=$poruka, link=$link, tip=$tip, procitana=$procitana, vrijeme=$vrijeme, student=$userid");
}


// Sortiramo po vremenu
arsort($vrijeme_poruke);
$count=0;


foreach ($vrijeme_poruke as $id=>$vrijeme) {
	if ($count==0) {
		// Polje pubDate u zaglavlju sadrži vrijeme zadnje izmjene tj. najnovije poruke

		//print "        <pubDate>".date(DATE_RSS, $vrijeme)."</pubDate>\n";
		// U verziji PHP 5.1.6 (i vjerovatno starijim) DATE_RSS je nekorektno 
		// izjednačeno sa "D, j M Y H:i:s T"
		print "        <pubDate>".date("D, j M Y H:i:s O", $vrijeme)."</pubDate>\n";
	}

	print $code_poruke[$id];
	$count++;
	if ($count==$broj_poruka) break; // prikazujemo 5 poruka
}




?>
</channel>
</rss>
