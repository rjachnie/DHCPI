<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Configuration extends MY_Controller {

    public function __construct() {
        parent::__construct();

        $this->load->model('mconfiguration');
        $this->load->model('mvlans');
        $this->load->model('mmachines');
        $this->load->model('mpartages');
    }

    public function index() {
        $this->grocery_crud->set_table('configuration');
        $this->grocery_crud->set_subject('Option globale');
        $this->grocery_crud->required_fields('Options');
        $output = $this->grocery_crud->render();
        $this->load->view('main.php', $output);
    }

    public function make() {

        $myFile = "/etc/dhcp/dhcpd.conf";
        $fh = fopen($myFile, 'w');

        $content = $this->declareDefault();
        $content = $content . $this->declarePartages();
        $content = $content . $this->declareSubnets();
        $content = $content . $this->declareHosts();

        if (fwrite($fh, $content)) {
            $this->session->set_flashdata('erreur', 'ok');
        } else {
            $this->session->set_flashdata('erreur', 'nok');
        }

        fclose($fh);
        $this->restartService();
        redirect();
    }

    private function declareDefault() {

        $query = $this->mconfiguration->get();
        $default = "# Fichier généré automatiquement par DHCPI #\n# Ne pas modifier manuellement ! #\n\n";
        $default = $default . "\n# Déclaration Configuration Globale #\n";
        foreach ($query->result() as $row) {
            $default = $default . $row->Options . ";\n";
        }

        return $default;
    }

    private function declarePartages() {
        $partages = "\n\n# Déclaration des Partages Réseaux #\n";

        if ($this->db->count_all('reseaux_partages') > 0) {
            $shares = $this->mpartages->get();

            foreach ($shares->result() as $row) {
                $partages = $partages . "\n#Réseau Partagé " . $row->Nom . "\n";
                $partages = $partages . "shared-network " . $row->Nom . " {\n";
                $vlans = $this->mvlans->getAllFromPartages($row->idreseaux_partages);
                foreach ($vlans->result() as $vlan) {
                    $partages = $partages . "\n\t# " . $vlan->Commentaire . "\n\tsubnet " . $vlan->IP . " netmask " . $vlan->Netmask . " {";
                    $partages = $partages . "\n" . $vlan->options_vlans;
                    if ($vlan->DNS_1 != "") {
                        $partages = $partages . "\noption domain-name-servers " . $vlan->DNS_1;
                        if ($vlan->DNS_2 != "") {
                            $partages = $partages . "," . $vlan->DNS_2;
                        }

                        $partages = $partages . ";";
                    }
                    $partages = $partages . "\n\t}\n";
                }
            }
            $partages = $partages . "\n}\n";
        }
        return $partages;
    }

    private function declareSubnets() {
        $subnets = "\n\n# Déclaration des Subnets #\n";
        if ($this->db->count_all('vlans') > 0) {
            $vlans = $this->mvlans->getEmptyShare();

            foreach ($vlans->result() as $vlan) {
                $subnets = $subnets . "\n# " . $vlan->Commentaire . "\nsubnet " . $vlan->IP . " netmask " . $vlan->Netmask . " {";
                $subnets = $subnets . "\n" . $vlan->options_vlans;
                if ($vlan->DNS_1 != "") {
                    $subnets = $subnets . "\noption domain-name-servers " . $vlan->DNS_1;
                    if ($vlan->DNS_2 != "") {
                        $subnets = $subnets . "," . $vlan->DNS_2;
                    }

                    $subnets = $subnets . ";";
                }
                $subnets = $subnets . "\n}\n";
            }
        }


        return $subnets;
    }

    private function declareHosts() {
        $hosts = "\n\n# Déclaration des Hosts #\n";
        if ($this->db->count_all('machines') > 0) {
            $query = $this->mmachines->get();

            foreach ($query->result() as $row) {
                $hosts = $hosts . "\n# " . $row->Commentaire . "\nhost " . $row->Nom . " {\n" . "\thardware ethernet " . $row->Adresse_MAC . ";\n\tfixed-address " . $row->IP . ";\n}\n";
            }
        }

        return $hosts;
    }

    private function restartService() {
        shell_exec("sudo /etc/init.d/isc-dhcp-server restart");
    }
    
        
    public function getDHCPstate() {
        $state = shell_exec("pidof dhcpd");echo $state;
        if ($state != "") {
            return "success";
            
        } else {
            return "alert";
        }
    }


}
