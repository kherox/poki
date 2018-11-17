<?php

    class ContentsControlleur extends controlleur
    {
        private $cfg;
        private $usr;

        public function __construct()
        {
            if (file_exists(ROOT . 'statics/config.php') && !isset($dbuser) && !isset($dbpass)) {
                include ROOT . 'statics/config.php';
                Config::$db_user = $dbuser;
                Config::$db_host = $dbhost;
                Config::$db_password = $dbpass;
                Config::$db_name = $dbname;
            }
            $this->usr = $this->loadController('users');
            $this->cfg = $this->loadController('config');
        }

        public function add()
        {
            $categoryname = Posts::post('category');
            $category = $this->loadModele('categories')->trouverCategory($categoryname);
            $edition = Posts::post('editing');

            $content = $this->getContentObject($category);

            if ($this->loadModele()->{ $edition!='0' && strlen(trim($edition)) ? 'modifierContent':'creerContent' }($content, $categoryname, $edition)) {
                $this->json_success("Content saved succefully !", ["newtoken" => Posts::getCSRFTokenValue()]);
                exit();
            }
            else {
                $this->json_error("An error occured. Please try again later.", ["newtoken" => Posts::getCSRFTokenValue()]);
                exit();
            }
        }

        public function addFromCsv()
        {
            Posts::disableCSRF();
            $file = Posts::file('csvfile');
            $categoryname = Posts::post('categoryname');
            $content = file_get_contents($file['tmp_name']);
            $categoryfields = $this->loadModele('categories')->trouverCategory($categoryname);
            try {
                $lines = dm::getFromCsv($content);
                # getting category fields in array
                $columns = [];
                foreach ($categoryfields as $k => $field) {
                    $columns[] = $field['name'];
                }
                # csv does correspond to category fields ?
                if (count($lines[0]) == count($columns)) {
                    $valuestring = [];
                    foreach ($lines as $k => $line) {
                        $linestring = implode(',', array_map(function ($item) { return "'". str_replace("'", "", $item) ."'"; }, $line));
                        $valuestring[] = '('. $linestring .')';
                    }
                    # query string
                    $left = implode(',', $columns);
                    $right = implode(',', $valuestring);
                    $q = 'INSERT INTO adm_app_' .$categoryname. ' ('. $left .') VALUES '. $right;
                    # adding in database
                    try {
                        $this->loadModele('categories')->getDbInstance()->exec($q);
                        echo $this->json_success("Well Done !");
                    }
                    catch (Exception $e) {
                        echo $this->json_error("An error occured ! Please try again later." . $e->getMessage());
                    }
                }
                else {
                    echo $this->json_error("Le fichier CSV n'est pas correctement formaté !");
                }
            }
            catch (Exception $e) {
                echo $this->json_error("Le fichier CSV n'est pas correctement formaté !");
            }
        }

        public function list($category)
        {
            return $this->loadModele()->trouverTousContents($category);
        }

        public function delete()
        {
            $contentid = Posts::get(0);
            $categoryname = Posts::get(1);

            if ($this->loadModele()->supprimerContents($categoryname, $contentid)) {
                $this->json_success("Content deleted !");
                exit();
            }
            else {
                $this->json_error("An error occured. Please try again later.");
                exit();
            }
        }

        private function getContentObject($categoryItems) {
            $object = [];
            foreach ($categoryItems as $k => $item) {
                if (!in_array($item['name'], ['id', 'active', 'added_at'])) {
                    if (Posts::post([$item['name']])) {
                        $object[$item['name']] = Posts::post($item['name']);
                    }
                    else {
                        $object = false;
                        break;
                    }
                }
            }
            return $object;
        }

        public function getCsv()
        {
            $categoryname = Posts::get(0);
            $lines = $this->loadModele()->trouverTousContents($categoryname);
            $heads = $this->loadModele('categories')->trouverCategory($categoryname);
            $files = [];
            # looping for header
            $head = [];
            foreach ($heads as $k => $col) {
                $head[] = $col['name'];
            }
            # looping for lines
            foreach ($lines as $k => $line) {
                $lines[$k] = implode(',', array_slice($line, 3));
            }
            # assembling
            $files[] = implode(',', $head);
            $files[] = implode("\n", $lines);
            $files = implode("\n", $files);
            # writing
            if (file_put_contents(ROOT . 'appfiles/fields_files/' . $categoryname . '.csv', $files)) {
                echo $this->json_success(WROOT . 'appfiles/fields_files/' . $categoryname . '.csv');
            }
            else {
                echo $this->json_error("Permission denied !");
            }
        }

        public function test()
        {
            var_dump(Posts::get([1]));
        }
    }
    