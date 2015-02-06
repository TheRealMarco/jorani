<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 * This file is part of Jorani.
 */

class Recuperation extends CI_Controller {
    
    /**
     * Default constructor
     * @author Benjamin BALET <benjamin.balet@gmail.com>
     */
    public function __construct() {
        parent::__construct();
        //Check if user is connected
        if (!$this->session->userdata('logged_in')) {
            $this->session->set_userdata('last_page', current_url());
            redirect('session/login');
        }
        $this->fullname = $this->session->userdata('firstname') . ' ' .
                $this->session->userdata('lastname');
        $this->is_hr = $this->session->userdata('is_hr');
        $this->load->model('recuperation_model');
		$this->load->model('roles_model');
        $this->user_id = $this->session->userdata('id');
        $this->language = $this->session->userdata('language');
        $this->language_code = $this->session->userdata('language_code');
        $this->load->helper('language');
        $this->lang->load('recuperation', $this->language);
    }
    
    /**
     * Prepare an array containing information about the current user
     * @return array data to be passed to the view
     * @author Benjamin BALET <benjamin.balet@gmail.com>
     */
    private function getUserContext()
    {
        $data['fullname'] = $this->fullname;
        $data['is_hr'] = $this->is_hr;
        $data['user_id'] =  $this->user_id;
        $data['language'] = $this->language;
        $data['language_code'] =  $this->language_code;
        return $data;
    }

    /**
     * Display the list of the leave requests of the connected user
     * @author Benjamin BALET <benjamin.balet@gmail.com>
     */
    public function index() {
        $this->auth->check_is_granted('list_recuperation');
        $this->expires_now();
        $data = $this->getUserContext();
        $data['recuperations'] = $this->recuperation_model->get_user_recuperations($this->user_id);
        
        $this->load->model('status_model');
        for ($i = 0; $i < count($data['recuperations']); ++$i) {
            $data['recuperations'][$i]['status_label'] = $this->status_model->get_label($data['recuperations'][$i]['status']);
        }
        $data['title'] = lang('recuperation_index_title');
        $this->load->view('templates/header', $data);
        $this->load->view('menu/index', $data);
        $this->load->view('recuperation/index', $data);
        $this->load->view('templates/footer');
    }
    
    /**
     * Display a leave request
     * @param int $id identifier of the leave request
     * @author Benjamin BALET <benjamin.balet@gmail.com>
     */
    public function view($id) {
        $this->auth->check_is_granted('view_recuperation');
        $data = $this->getUserContext();
        $data['recuperation'] = $this->recuperation_model->get_recuperation($id);
        $this->load->model('status_model');
        if (empty($data['recuperation'])) {
            show_404();
        }
        $data['recuperation']['status_label'] = $this->status_model->get_label($data['recuperation']['status']);
        $data['title'] = lang('recuperation_view_hmtl_title');
        $this->load->view('templates/header', $data);
        $this->load->view('menu/index', $data);
        $this->load->view('recuperation/view', $data);
        $this->load->view('templates/footer');
    }
	
	public function pdf_myrecuperation($id) {
		$this->auth->check_is_granted('view_myprofile');
        $data = $this->getUserContext();
		
		$data['user'] = $this->user_id;
        if (empty($data['user'])) {
            show_404();
        }
		
		$pathConfigFile = realpath(join(DIRECTORY_SEPARATOR, array('application', 'config', 'database.php')));
		if (file_exists($pathConfigFile)) { // Pour rqt sql
			include $pathConfigFile;
		}
		
		$cnx = mysqli_connect($db['default']['hostname'], $db['default']['username'], $db['default']['password']);
		if (! $cnx)
		{
			echo "Connexion au serveur impossible !";
			mysqli_close($cnx);
			exit();
		}
		$labd=mysqli_select_db($cnx,$db['default']['database']);
		if (! $labd)
		{
			echo "Connexion à la base de données impossible !";
			mysqli_close($cnx);
			exit();
		}
		
		$requete=mysqli_query($cnx,"SELECT date, HOUR(heureDebut) AS heureDebut, 
		MINUTE(heureDebut) AS minuteDebut, HOUR(heureFin) AS heureFin, MINUTE(heureFin) AS minuteFin, duration 
		FROM recuperation WHERE id=".$id);	// Fait la requête
		if($requete!=null) {
			$ligne= mysqli_fetch_assoc($requete);
			$data['duree'] = $ligne['duration'];
			$data['heureDebut'] = $ligne['heureDebut'];
			$data['minuteDebut'] = $ligne['minuteDebut'];
			$data['heureFin'] = $ligne['heureFin'];
			$data['minuteFin'] = $ligne['minuteFin'];
			$data['date'] = strftime('%d / %m / %Y',strtotime($ligne['date']));
		}
		
		$data['matin'] = "X"; $data['aprem'] = "X"; $data['journee'] = "X";
		
		if ($ligne['heureDebut'] > 12) {
			$data['matin'] = "&nbsp;";
		}
		if ($ligne['heureFin'] < 12) {
			$data['aprem'] = "&nbsp;";
		}
		if ($ligne['heureDebut'] > 12 || $ligne['heureFin'] < 12) {
			$data['journee'] = "&nbsp;";
		}
		
        $data['title'] = 'Demande de conge';
        $this->load->model('roles_model');
        $this->load->model('positions_model');
        $this->load->model('contracts_model');
        $this->load->model('organization_model');
        $data['roles'] = $this->roles_model->get_roles();
        $this->load->helper('pdf_helper');
        
        tcpdf();
        $obj_pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $obj_pdf->SetCreator(PDF_CREATOR);
        $title = "Demande d'absence";
        $obj_pdf->SetTitle($title);
        $obj_pdf->SetAuthor($this->fullname);
        $obj_pdf->SetSubject('Jorani absence');
        $obj_pdf->SetKeywords('Jorani, demande, absence');
		$obj_pdf->setPrintFooter(false);
		$obj_pdf->setPrintHeader(false);
        $obj_pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, $title, '');
        $obj_pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        //$obj_pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        //$obj_pdf->SetDefaultMonospacedFont('freeserif');
        $obj_pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        //$obj_pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $obj_pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $obj_pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $obj_pdf->SetFont('helvetica', '', 9);
        $obj_pdf->SetDefaultMonospacedFont('freemono');
        //$obj_pdf->SetDefaultMonospacedFont('khmeros');
        //$obj_pdf->SetFont('khmeros','b',12);
        $obj_pdf->setFontSubsetting(true);
        $obj_pdf->AddPage();
        ob_start();

        $this->load->view('templates/pdf_header', $data);
        $this->load->view('recuperation/pdf_myrecuperation', $data);
        //$this->load->view('templates/pdf_footer');
        $content = ob_get_contents();
        //$content = iconv("UTF-8", "ISO-8859-1", $content);
        
        ob_end_clean();
        $obj_pdf->writeHTML($content, true, false, true, false, '');
        $obj_pdf->Output('Demande_autorisation_absence_'.$this->fullname.'.pdf', 'I');
	}

    /**
     * Create a leave request
     * @author Benjamin BALET <benjamin.balet@gmail.com>
     */
    public function create() {
        $this->auth->check_is_granted('create_recuperation');
        $data = $this->getUserContext();
        $this->load->helper('form');
        $this->load->library('form_validation');
        $data['title'] = lang('recuperation_recuperation_title');
		
		// Recherche du nombre d'heures supplémentaires
		  
			// Cherche le fichier config.
			$pathConfigFile = realpath(join(DIRECTORY_SEPARATOR, array('application', 'config', 'database.php')));
			if (file_exists($pathConfigFile)) {
				include $pathConfigFile;
			}

			//Connexion au serveur et à la BD
			$cnx = mysqli_connect($db['default']['hostname'], $db['default']['username'], $db['default']['password']);
			if (! $cnx)
			{
				echo "Connexion au serveur impossible !";
				mysqli_close($cnx);
				exit();
			}
			$labd=mysqli_select_db($cnx,$db['default']['database']);
			if (! $labd)
			{
				echo "Connexion à la base de données impossible !";
				mysqli_close($cnx);
				exit();
			}
			//Heures supp récupérées
			$requete=mysqli_query($cnx,"SELECT SUM(duration) FROM recuperation WHERE employee=".$data['user_id'].' AND status = 3');	// Fait la requête
			if($requete!=null
			){
				$ligne= mysqli_fetch_array($requete);
				$data['solde'] = $ligne[0];
				// Heures supp faites
				$requete=mysqli_query($cnx,"SELECT SUM(duration) FROM overtime WHERE status != 4 AND employee=".$data['user_id'].' AND status = 3');	// Fait la requête
				$ligne= mysqli_fetch_array($requete);
				$data['solde'] =  $ligne[0]-$data['solde'] ;
			}
			else
			{$data['solde'] = 0;}
			
        $this->form_validation->set_rules('date', lang('recuperation_recuperation_field_date'), 'required|xss_clean');
        $this->form_validation->set_rules('duration', lang('recuperation_recuperation_field_duration'), 'required|xss_clean|callback_checkRecupDuree['.$data['solde'].']');
        $this->form_validation->set_rules('status', lang('recuperation_recuperation_field_status'), 'required|xss_clean');
		
        if ($this->form_validation->run() === FALSE) {
            $this->load->view('templates/header', $data);
            $this->load->view('menu/index', $data);
            $this->load->view('recuperation/create');
            $this->load->view('templates/footer');
        } else {
            $recuperation_id = $this->recuperation_model->set_recuperation();
            $this->session->set_flashdata('msg', lang('recuperation_recuperation_msg_success'));
            //If the status is requested, send an email to the manager

			$this->sendManagerMail($recuperation_id);
			$this->sendManagerMail($this->user_id);

            if (isset($_GET['source'])) {
                redirect($_GET['source']);
            } else {
                redirect('recuperation');
            }
        }
    }

	/*
	* Fonction de vérification de la durée de la view CREATE.
	*/
	function checkRecupDuree($in,$nb)
	{	
		if ($in>8)
		{
			$this->form_validation->set_message('checkRecupDuree', 'Vous ne pouvez poser que 8 heures au maximum pour cette journée.');
			return FALSE;
		}
		
		if($in > $nb)
		{
			$this->form_validation->set_message('checkRecupDuree', "Vous n'avez pas effectuée avec d'heures supp.");
			return FALSE;
		}
		return TRUE;
	}
    
    /**
     * Send a recuperation request email to the manager of the connected employee
     * @param int $id Leave request identifier
     * @author Benjamin BALET <benjamin.balet@gmail.com>
     */
    private function sendManagerMail($id) {	// To the manager
        $this->load->model('users_model');
        $this->load->model('settings_model');
        $manager = $this->users_model->get_users($this->session->userdata('manager'));

        //Test if the manager hasn't been deleted meanwhile
        if (empty($manager['email'])) {
            $this->session->set_flashdata('msg', lang('recuperation_create_msg_error'));
        } else {
            $acceptUrl = base_url() . 'recuperation/accept/' . $id;
            $rejectUrl = base_url() . 'recuperation/reject/' . $id;

            //Send an e-mail to the manager
            $this->load->library('email');
            $this->load->library('polyglot');
            $usr_lang = $this->polyglot->code2language($manager['language']);
            $this->lang->load('email', $usr_lang);

            $this->lang->load('global', $usr_lang);
            $date = new DateTime($this->input->post('date'));
            $startdate = $date->format(lang('global_date_format'));

            $this->load->library('parser');
            $data = array(
                'Title' => lang('email_recup_request_validation_title'),
                'Firstname' => $this->session->userdata('firstname'),
                'Lastname' => $this->session->userdata('lastname'),
                'Date' => $startdate,
                'Duration' => $this->input->post('duration'),
                'UrlAccept' => $acceptUrl,
                'UrlReject' => $rejectUrl
            );
            $message = $this->parser->parse('emails/' . $manager['language'] . '/recuperation', $data, TRUE);
			
            if ($this->email->mailer_engine == 'phpmailer') {
                $this->email->phpmailer->Encoding = 'quoted-printable';
            }
            if ($this->config->item('from_mail') != FALSE && $this->config->item('from_name') != FALSE ) {
                $this->email->from($this->config->item('from_mail'), $this->config->item('from_name'));
            } else {
               $this->email->from('do.not@reply.me', 'LMS');
            }
            $this->email->to($manager['email']);
            $this->email->subject(lang('email_recup_request_reject_subject') .
                    $this->session->userdata('firstname') . ' ' .
                    $this->session->userdata('lastname'));
            $this->email->message($message);
            $this->email->send();
        }
    }
	
	// Envoie email a lemployee
	private function sendMail($id) {
		$this->load->model('users_model');
		$this->load->model('settings_model');
		$manager = $this->users_model->get_users($this->session->userdata('manager'));

		$this->load->library('email');
		$this->load->library('polyglot');
		$usr_lang = $this->polyglot->code2language($manager['language']);
		$this->lang->load('email', $usr_lang);

		$this->lang->load('global', $usr_lang);
		$date = new DateTime($this->input->post('date'));
		$startdate = $date->format(lang('global_date_format'));

		$this->load->library('parser');
		$data = array(
			'Title' => "Demande d'autorisation d'absence",
			'Firstname' => $this->session->userdata('firstname'),
			'Lastname' => $this->session->userdata('lastname'),
			'Date' => $startdate,
			'Duration' => $this->input->post('duration'),
			'heureDebut' => $this->input->post('heureDebut'),
			'minuteDebut' => $this->input->post('minuteDebut'),
			'heureFin' => $this->input->post('heureFin'),
			'minuteFin' => $this->input->post('minuteFin')
		);
		$message = $this->parser->parse('emails/' . $manager['language'] . '/self_recup', $data, TRUE);
		
		$string = "Normalement ce texte se trouve dans un pdf en piece jointe.";
		$this->email->AddAttachment($string, 'MaDemandeDautorisation', 'base64','pdf');
		// $this->email->AddAttachment($string,$filename,$encoding,$type);
		
		if ($this->email->mailer_engine == 'phpmailer') {
			$this->email->phpmailer->Encoding = 'quoted-printable';
		}
		if ($this->config->item('from_mail') != FALSE && $this->config->item('from_name') != FALSE ) {
			$this->email->from($this->config->item('from_mail'), $this->config->item('from_name'));
		} else {
		   $this->email->from('do.not@reply.me', 'LMS');
		}
		$this->email->to($manager['email']);
		$this->email->subject(lang('email_recup_request_reject_subject') .
				$this->session->userdata('firstname') . ' ' .
				$this->session->userdata('lastname'));
		$this->email->message($message);
		$this->email->send();
    }

    /**
     * Delete a leave request
     * @param int $id identifier of the leave request
     * @author Benjamin BALET <benjamin.balet@gmail.com>
     */
    public function delete($id) {
        $can_delete = false;
        //Test if the leave request exists
        $recuperation = $this->recuperation_model->get_recuperation($id);
        if (empty($recuperation)) {
            show_404();
        } else {
            if ($this->is_hr) {
                $can_delete = true;
            } else {
                if ($leaves['status'] == 1 ) {
                    $can_delete = true;
                }
            }
            if ($can_delete == true) {
                $this->recuperation_model->delete_recuperation($id);
                $this->session->set_flashdata('msg', lang('recuperation_delete_msg_success'));
            } else {
                $this->session->set_flashdata('msg', lang('recuperation_delete_msg_error'));
            }
        }
        if (isset($_GET['source'])) {
            redirect($_GET['source']);
        } else {
            redirect('recuperation');
        }
    }
    
    /**
     * Action: export the list of all heures supplementaires into an Excel file
     */
    public function export() {
		$this->expires_now();
		$this->load->library('excel');
        $this->excel->setActiveSheetIndex(0);
		
        $this->excel->getActiveSheet()->setTitle(lang('recup_export_title'));
        $this->excel->getActiveSheet()->setCellValue('A1', lang('recup_index_thead_id'));
        $this->excel->getActiveSheet()->setCellValue('B1', lang('recup_export_thead_date'));
        $this->excel->getActiveSheet()->setCellValue('C1', lang('recup_index_thead_duration').' (en heures)');
        $this->excel->getActiveSheet()->setCellValue('D1', lang('recup_index_thead_status'));
        
        $recups = $this->recuperation_model->get_user_recuperations($this->user_id);
        $this->load->model('status_model');
		$this->load->model('types_model');
        
        $line = 2;
        foreach ($recups as $recup) {
            $this->excel->getActiveSheet()->setCellValue('A' . $line, $recup['id']);
            $this->excel->getActiveSheet()->setCellValue('B' . $line, $recup['date']);
            $this->excel->getActiveSheet()->setCellValue('C' . $line, $recup['duration']);
            $this->excel->getActiveSheet()->setCellValue('D' . $line, $this->status_model->get_label_fr($recup['status']));
            $line++;
        }
		
        $filename = 'Mes heures supp récupérées.xls';
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
        $objWriter->save('php://output');
    }
	
	public function bilan() {
		$this->expires_now();
		$this->load->library('excel');
        $this->excel->setActiveSheetIndex(0);
		
		$pathConfigFile = realpath(join(DIRECTORY_SEPARATOR, array('application', 'config', 'database.php')));
		if (file_exists($pathConfigFile)) { // Pour rqt sql
			include $pathConfigFile;
		}
		//Connexion au serveur et à la BD
		$cnx = mysqli_connect($db['default']['hostname'], $db['default']['username'], $db['default']['password']);
		if (! $cnx)
		{
			echo "Connexion au serveur impossible !";
			mysqli_close($cnx);
			exit();
		}
		$labd=mysqli_select_db($cnx,$db['default']['database']);
		if (! $labd)
		{
			echo "Connexion à la base de données impossible !";
			mysqli_close($cnx);
			exit();
		}
		
		// Première partie -> colorée
        $this->excel->getActiveSheet()->setTitle(lang('bilan_title'));
		$this->excel->getActiveSheet()->setCellValue('D1', lang('bilan_etablis'));
		$this->excel->getActiveSheet()->setCellValue('D2', lang('bilan_title'));
		$this->excel->getActiveSheet()->getStyle('D1:D2')->getFont()->setBold(true);
        $this->excel->getActiveSheet()->getStyle('D1:D2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
		
		// Présentation personne
		$requete = mysqli_query($cnx,"SELECT name FROM users, roles WHERE users.ID = '".$this->user_id."' AND users.role = roles.id");	// Fait la requête
		$role = mysqli_fetch_array($requete);
		
		$this->excel->getActiveSheet()->setCellValue('B4', $role[0]);
		$this->excel->getActiveSheet()->setCellValue('C4', $this->fullname);
		$this->excel->getActiveSheet()->setCellValue('B5', lang('recup_index_thead_date'));
		$this->excel->getActiveSheet()->setCellValue('C5', date("d / m / Y"));
		
		// Heures effectuées
		$requete = mysqli_query($cnx,"SELECT SUM(duration) AS 'total' FROM overtime WHERE overtime.employee = '".$this->user_id."' AND status = 3");	// Fait la requête
		$totalEffec = mysqli_fetch_array($requete);
		
		if ($totalEffec[0] == 0) { $totalEffec[0] = "0"; }	// Excel n'affiche pas les valeurs nulles donc on convertie en chaine de caractères
		
		$this->excel->getActiveSheet()->setCellValue('B8', 'Heures effectuées');
		$this->excel->getActiveSheet()->setCellValue('B9', 'Total');
		$this->excel->getActiveSheet()->setCellValue('C9', $totalEffec[0]);
		$this->excel->getActiveSheet()->setCellValue('B10', 'Date');
		$this->excel->getActiveSheet()->setCellValue('C10', "Nb d'heures");
		
		// Tableau
		$requete = mysqli_query($cnx,"SELECT date, duration, cause FROM overtime WHERE overtime.employee = ".$this->user_id." AND status = 3");	// Fait la requête 
		$lineG = 11;
		
		while ($ligne= mysqli_fetch_assoc($requete)) {
			$this->excel->getActiveSheet()->setCellValue('A' . $lineG, utf8_encode($ligne['cause']));
			$this->excel->getActiveSheet()->setCellValue('B' . $lineG, $ligne['date']);
			$this->excel->getActiveSheet()->setCellValue('C' . $lineG, $ligne['duration']);
			
            $lineG++;
		}
		
		// Heures récupérées
		$requete = mysqli_query($cnx,"SELECT SUM(duration) AS 'total' FROM recuperation WHERE recuperation.employee = '".$this->user_id."' AND status = 3");	// Fait la requête
		$totalRecup = mysqli_fetch_array($requete);
		
		$diff = $totalEffec[0] - $totalRecup[0];
		if ($totalRecup[0] == 0) { $totalRecup[0] = "0"; }	// Excel n'affiche pas les valeurs nulles
		if ($diff == 0) { $diff = "0"; }
		
		$this->excel->getActiveSheet()->setCellValue('E5', 'Total heures sup disponibles');
		$this->excel->getActiveSheet()->setCellValue('E6', $diff);
		$this->excel->getActiveSheet()->setCellValue('E8', 'Heures récupérées');
		$this->excel->getActiveSheet()->setCellValue('E9', 'Total');
		$this->excel->getActiveSheet()->setCellValue('F9', $totalRecup[0]);
		$this->excel->getActiveSheet()->setCellValue('E10', 'Date');
		$this->excel->getActiveSheet()->setCellValue('F10', "Nb d'heures");
		
		// Tableau
		$requete = mysqli_query($cnx,"SELECT date, duration FROM recuperation WHERE recuperation.employee = ".$this->user_id." AND status = 3");	// Fait la requête 
		$lineD = 11;
		
		while ($ligne= mysqli_fetch_assoc($requete)) {
			$this->excel->getActiveSheet()->setCellValue('E' . $lineD, $ligne['date']);
			$this->excel->getActiveSheet()->setCellValue('F' . $lineD, $ligne['duration']);
			
            $lineD++;
		}
		
		$lineD = $lineD-1; $lineG = $lineG-1; // Réutilisation pour le calcul des bordures des tableaux
		
		// Mise en forme
		// Couleur, alignement et gras
		// Bordure de tableau. Je créé un style, et je l'applique ensuite par copie.
		$tableau = $this->excel->getActiveSheet()->getStyle('E8:F10');
		$tableau->applyFromArray(array( 
			'borders' => array(
			'outline' => array(
			'style' => PHPExcel_Style_Border::BORDER_THIN),
			'inside' => array(
			'style' => PHPExcel_Style_Border::BORDER_THIN
		))));
		$this->excel->getActiveSheet()->duplicateStyle($tableau, 'B8:C10');
		$this->excel->getActiveSheet()->duplicateStyle($tableau, 'E11:F'.$lineD);
		$this->excel->getActiveSheet()->duplicateStyle($tableau, 'A11:C'.$lineG);
		
		$this->excel->getActiveSheet()->getStyle('D1:D2')->getFont()->setBold(true);
		$this->excel->getActiveSheet()->getStyle('C9')->getFont()->setBold(true);
		$this->excel->getActiveSheet()->getStyle('F9')->getFont()->setBold(true);
		$this->excel->getActiveSheet()->getStyle('B8:E8')->getFont()->setBold(true);
        $this->excel->getActiveSheet()->getStyle('D1:D2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
		$this->excel->getActiveSheet()->getStyle('B8:E8')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
		$this->excel->getActiveSheet()->getStyle('D1')->getFont()->setSize(20);
		$this->excel->getActiveSheet()->getStyle('D2')->getFont()->setSize(16);
		$this->excel->getActiveSheet()->getStyle('D1:D2')->getFont()->getColor()->setARGB(PHPExcel_Style_Color::COLOR_DARKBLUE);
		$this->excel->getActiveSheet()->getStyle('C9')->applyFromArray(array( 'fill' => 
		array( 'type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb' => 'B0B0B0'))));
		$this->excel->getActiveSheet()->getStyle('F9')->applyFromArray(array( 'fill' => 
		array( 'type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb' => 'B0B0B0'))));
		$this->excel->getActiveSheet()->getStyle('E6')->applyFromArray(array( 'fill' => 
		array( 'type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb' => 'B0B0B0'))));
		
		
		// Fusionner des cellules
		$this->excel->getActiveSheet()->mergeCells('B8:C8');
		$this->excel->getActiveSheet()->mergeCells('E8:F8');
		$this->excel->getActiveSheet()->mergeCells('E5:F5');
		$this->excel->getActiveSheet()->mergeCells('C4:D4');
		
		// Aligner une colonne par rapport à son contenu.
		$this->excel->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
		$this->excel->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
		$this->excel->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
		$this->excel->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
		$this->excel->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);
		
		// Fin
        $filename = 'bilan.xls';
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel5');
        $objWriter->save('php://output');
    }
    
    /**
     * Internal utility function
     * make sure a resource is reloaded every time
     */
    private function expires_now() {
        // Date in the past
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        // always modified
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        // HTTP/1.1
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        // HTTP/1.0
        header("Pragma: no-cache");
    }
}
