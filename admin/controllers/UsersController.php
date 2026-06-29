<?php
Auth::requireRole('admin');
require_once __DIR__ . '/../models/User.php';

switch ($action) {
    case 'new':
    case 'edit':
        $id   = Sanitize::posInt($_GET['id'] ?? 0);
        $user = $id ? User::byId($id) : null;
        if ($id && !$user) { Flash::error('Utilizatorul nu exista.'); header('Location: /admin/?page=users'); exit; }
        ob_start();
        require __DIR__ . '/../views/users/edit.php';
        $bodyContent = ob_get_clean();
        $pageTitle = $id ? 'Editare utilizator' : 'Utilizator nou';
        require __DIR__ . '/../views/layout.php';
        break;

    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=users'); exit; }
        Csrf::verify();
        $id       = Sanitize::posInt($_POST['id'] ?? 0);
        $username = Sanitize::text($_POST['username'] ?? '', 60);
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $role     = in_array($_POST['role']??'', ['admin','editor'], true) ? $_POST['role'] : 'editor';
        $password = $_POST['password'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!$username || !$email) {
            Flash::error('Username si email sunt obligatorii.');
            header('Location: /admin/?page=users&action=' . ($id ? 'edit&id='.$id : 'new')); exit;
        }
        if (!$id && strlen($password) < 8) {
            Flash::error('Parola trebuie sa aiba minim 8 caractere.');
            header('Location: /admin/?page=users&action=new'); exit;
        }
        if (User::usernameExists($username, $id)) {
            Flash::error('Username-ul exista deja.');
            header('Location: /admin/?page=users&action=' . ($id ? 'edit&id='.$id : 'new')); exit;
        }
        $data = ['username'=>$username,'email'=>$email,'role'=>$role,'is_active'=>$isActive,'password'=>$password];
        if ($id) {
            User::update($id, $data);
            Flash::success('Utilizatorul a fost actualizat.');
        } else {
            User::create($data);
            Flash::success('Utilizator creat cu succes.');
        }
        header('Location: /admin/?page=users'); exit;

    case 'toggle':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/?page=users'); exit; }
        Csrf::verify();
        $id = Sanitize::posInt($_POST['id'] ?? 0);
        if ($id === Auth::id()) { Flash::error('Nu poti dezactiva propriul cont.'); header('Location: /admin/?page=users'); exit; }
        $u = User::byId($id);
        if ($u) {
            Database::get()->prepare('UPDATE users SET is_active = ? WHERE id = ?')
                ->execute([$u['is_active'] ? 0 : 1, $id]);
            Flash::success('Status actualizat.');
        }
        header('Location: /admin/?page=users'); exit;

    default:
        $users = User::all();
        ob_start();
        require __DIR__ . '/../views/users/list.php';
        $bodyContent = ob_get_clean();
        $pageTitle = 'Utilizatori';
        require __DIR__ . '/../views/layout.php';
}
