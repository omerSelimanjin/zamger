<?

// COMMON/PROFIL + opcije korisnika

function common_profil() {

	global $userid, $conf_system_auth, $conf_files_path, $conf_promjena_sifre, $conf_skr_naziv_institucije, $conf_skr_naziv_institucije_genitiv;
	global $user_student, $user_nastavnik, $user_studentska, $user_siteadmin;
	
	
	$akcija = $_REQUEST['akcija'];
	
	// Ispis menija
	include("common/profil/top_meni.php");
	
	// Izbor opcije iz menia
	switch($akcija) {
		case "opcije": 
			include("common/profil/opcije.php");
			break;
		case "izbori": 
			include("common/profil/izbor_imenovanja.php");
			break;
		case "ljudskiresursi": 
			include("common/profil/ljudski_resursi.php");
			break;
		default: 
			include("common/profil/licni_podaci.php");
			break;	
	}
}
?>