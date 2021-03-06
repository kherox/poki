<?php

    class CategoriesControlleur extends controlleur
    {
        private $usr;
        private $cfg;

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

        public function create()
        {
            $name = Posts::post('name');
            $edition = Posts::post('editing');
            $category = (object) ["name" => $name, "oldname" => $edition];
            if (!strlen(trim($name))) {
                $this->json_error('Category name cannot be empty !', ["newtoken" => Posts::getCSRFTokenValue()]);
                exit();
            }
            else if (preg_match("#\s#", trim($name))) {
                $this->json_error('A category should not contain whitespace !', ["newtoken" => Posts::getCSRFTokenValue()]);
                exit();
            }
            else if ($this->loadModele()->existsCategory($name) && $name != $edition) {
                $this->json_error('A category with this name already exists !', ["newtoken" => Posts::getCSRFTokenValue()]);
                exit();
            }
            else if ($name == $edition) {
                $this->json_error('No change done !', ["newtoken" => Posts::getCSRFTokenValue()]);
                exit();
            }
            else {
                if ($this->loadModele()->{ $edition != '0' ? 'modifierCategory':'creerCategory' }($category)) {
                    $this->loadModele('params')->{ $edition != '0' ? 'updateCategoryParams':'createCategoryParams' }($category);
                    $this->json_success('Category created !', ["newtoken" => Posts::getCSRFTokenValue(), "name" => $name]);
                    exit();
                }
                else {
                    $this->json_error('An error occured ! Try again later.', ["newtoken" => Posts::getCSRFTokenValue()]);
                    exit();
                }
            }
        }

        public function delete()
        {
            $name = Posts::get(0);

            if ($this->loadModele()->supprimerCategory($name)) {
                $this->loadModele('params')->deleteCategoryParams($name);
                $this->json_success('Deleted !');
                exit();
            }
            else {
                $this->json_error('An error occured ! Try again later.', ["newtoken" => Posts::getCSRFTokenValue()]);
                exit();
            }
        }
        
        public function list()
        {
            $list = $this->loadModele()->trouverTousCategories();
            return $list;
        }

        public function show()
        {

            $this->cfg->configSurvey(false);
            $admin = $this->usr->loginSurvey(false, 'login');

            if (!Posts::get([0]) || !$this->loadModele()->existsCategory(Posts::get(0))) {
                $this->redirTo(Routes::find('dashboard'));
                exit();
            }
            else if ($admin->role != 'admin') {
                $this->redirTo(Routes::find('category-list') .'/'. Posts::get(0));
            }
            else {
                $categorie = $this->loadModele()->trouverCategory(Posts::get(0));
                $allfields = $this->loadModele()->trouverTousCategoryFields();
                $category_api = $this->loadModele('api')->trouverApi(Posts::get(0));
                $apitypes = $this->loadModele('settings')->get('apipermissiontypes');
                $this->render('app/category-show', [
                    "admin" => $admin,
                    "pagetitle" => "Category: " . Posts::get(0),
                    "categories" => $this->list(),
                    "category_name" => Posts::get(0),
                    "category_fields" => $categorie,
                    "all_category_fileds" => $allfields,
                    "apitypes" => explode(',', $apitypes->content),
                    "api" => $category_api
                ]);
            }
        }

        public function addField($edition=false)
        {
            $fields = [];
            $field_main = (object) [
                "name" => Posts::post('fieldname'),
                "type" => Posts::post('fieldtype'),
                "category" => Posts::post('category'),
                "oldname" => Posts::post('editing')
            ];
            $fields[] = $field_main;

            if (Posts::post(['fieldname_1'])) {
                $i = 1;
                while (Posts::post(['fieldname_' . $i])) {
                    $fields[] = (object) [
                        "name" => Posts::post('fieldname_' . $i),
                        "type" => Posts::post('fieldtype_' . $i),
                        "category" => Posts::post('category'),
                        "oldname" => Posts::post('editing')
                    ];
                    $i++;
                }
            }

            $this->checkFieldValues($fields);
            
            if ($this->loadModele()->{ $edition ? 'modifierCategoryField' : 'creerCategoryField' }($edition ? $field_main : $fields)) {
                $this->json_success('New fields added to !', [
                    "newtoken" => Posts::getCSRFTokenValue(),
                    "addedfields" => $fields
                ]);
                exit();
            }
            else {
                $this->json_error('An error occured. Try again.', ["newtoken" => Posts::getCSRFTokenValue()]);
                exit();
            }
        }

        public function editField()
        {
            $this->addField(true);
        }

        public function deleteField()
        {
            $field = Posts::get(1);
            $category = Posts::get(0);
            if ($this->loadModele()->supprimerCategoryField($field, $category)) {
                $this->json_success('Field completely deleted !');
                exit();
            }
            else {
                $this->json_success('An error occured ! Try again later.');
                exit();
            }
        }

        public function linkField()
        {
            if ($admin = $this->usr->loginSurvey(false, false, false)) {
                $category = Posts::get(0);
                $field = Posts::get(1);
                $linkto = Posts::get(2) != '0' ? Posts::get(2) . '/' . Posts::get(3) : $linkto = '0';

                if ($this->loadModele('params')->setLink($category, $field, $linkto)) {
                    echo $this->json_success("Link correctely setted !");
                }
                else {
                    echo $this->json_error("An error occurred. Please try again later.");
                }
            }
            else {
                echo $this->json_error("Vous devez être connecter pour effectuer cette operation !");
            }
        }

        public function form()
        {
            $name = Posts::get(0);
            $contentid = Posts::get([1]) ? Posts::get(1) : false;
            $content = false;

            $this->cfg->configSurvey(false);
            $admin = $this->usr->loginSurvey(false, 'login');

            if (!$this->loadModele()->existsCategory($name) || ($contentid && !($content = $this->loadModele('contents')->trouverContents($name, $contentid)))) {
                $this->redirTo(Routes::find('dashboard'));
                exit();
            }
            else {
                // var_dump($content);exit;
                $categorie = $this->loadModele()->trouverCategory($name);
                $this->render('app/category-form', [
                    "admin" => $admin,
                    "pagetitle" => "Category form: " . $name,
                    "categories" => $this->list(),
                    "category_name" => $name,
                    "category_fields" => $categorie,
                    "content" => $content
                ]);
            }
        }

        public function listContents()
        {
            $name = Posts::get(0);
            $limit = Posts::get([1]) ? (((Posts::get(1)-1)*30) . ', 30') : '0, 30';

            $this->cfg->configSurvey(false);
            $admin = $this->usr->loginSurvey(false, 'login');

            if (!$this->loadModele()->existsCategory($name)) {
                $this->redirTo(Routes::find('dashboard'));
                exit();
            }
            else {
                $category = $this->loadModele()->trouverCategory($name);
                $contents = $this->loadController('contents')->list($name, ["limit" => $limit]);
                $contentsNumber = $this->loadModele('contents')->compterContents($name);
                $this->render('app/category-list', [
                    "admin" => $admin,
                    "pagetitle" => 'Category contents: <a href="'. Routes::find('category-show') . '/' . $name .'">' . $name . '</a>',
                    "categories" => $this->list(),
                    "category_name" => $name,
                    "category_fields" => $category,
                    "nbrcontents" => $contentsNumber,
                    "contents" => $contents
                ]);
            }
        }

        public function checkFieldValues($fields)
        {
            foreach ($fields as $k => $field) {
                $sname = explode(' ', (trim($field->name)));
                
                if (count($sname) > 1 || strlen($field->name)==0) {
                    $this->json_error('Field name might not contain whitespaces !', ["newtoken" => Posts::getCSRFTokenValue()]);
                    exit();
                }
                else if ($field->type=='0') {
                    $this->json_error('You have to choose a type for this field !', ["newtoken" => Posts::getCSRFTokenValue()]);
                    exit();
                }
                else if (!isset(Helpers::$types[$field->type])) {
                    $this->json_error('This type is not allowed and should break your app !', ["newtoken" => Posts::getCSRFTokenValue()]);
                    exit();
                }
                else if ($this->checkFieldExists($field->name, $field->category)) {
                    if ($field->oldname != $field->name) {
                        $this->json_error('This field name already exists. Choose another one.', ["newtoken" => Posts::getCSRFTokenValue()]);
                        exit();
                    }
                }
            }
        }

        private function checkFieldExists($field, $category) {
            return $this->loadModele()->existsFieldCategory($category, $field);
        }
    }
    