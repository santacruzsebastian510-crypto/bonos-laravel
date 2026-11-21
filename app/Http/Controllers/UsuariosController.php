<?php
namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Http\Request;
use PDOException;

/** Administración de usuarios, roles y categorías. */
class UsuariosController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q', ''));
        $rolPedido = (string)$request->query('rol', '');
        $fr = isset(ROLES[$rolPedido]) ? $rolPedido : '';
        return view('usuarios.index', [
            'q' => $q, 'fr' => $fr, 'usuarios' => Usuario::listar($q, $fr),
            'conteo' => Usuario::conteoPorRol(), 'conteoCat' => Usuario::conteoPorCategoria(),
            'titulo' => 'Usuarios',
        ]);
    }

    public function store(Request $request)
    {
        $accion = $this->campo($request, 'accion');
        $rol = $this->campo($request, 'rol');
        if ($accion === 'crear') {
            $this->crear($request, $rol);
        } elseif ($accion === 'cargar_lista') {
            $this->cargarLista($request);
        } elseif ($accion === 'guardar') {
            $this->guardar($request, $rol);
        }
        return redirect('usuarios?' . http_build_query(['q' => $this->campo($request, 'q'), 'rol' => $this->campo($request, 'filtro_rol')]));
    }

    private function crear(Request $request, string $rol): void
    {
        $codigo = trim($this->campo($request, 'codigo'));
        $nombre = trim($this->campo($request, 'nombre'));
        $email = trim($this->campo($request, 'email'));
        $pass = $this->campo($request, 'password');
        $cat = $this->campo($request, 'categoria');
        $cat = isset(CATEGORIAS[$cat]) ? $cat : 'regular';
        if (!preg_match('/^[A-Za-z0-9._-]{3,20}$/', $codigo) || $nombre === '' || !isset(ROLES[$rol]) || strlen($pass) < 8
            || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            flash('error', 'Revisa los datos: código (3–20 caracteres), nombre, correo válido, rol y contraseña de al menos 8 caracteres.');
            return;
        }
        try {
            $id = Usuario::crear($codigo, $nombre, $email, $pass, $rol, $cat);
            Auditoria::registrar('usuario_creado', 'usuario', $id, ['codigo' => $codigo, 'rol' => $rol]);
            flash('ok', "Usuario $codigo creado.");
        } catch (PDOException $e) {
            flash('error', $e->getCode() === '23000' ? 'Ya existe un usuario con ese código.' : 'No se pudo crear el usuario.');
        }
    }

    private function cargarLista(Request $request): void
    {
        $cat = $this->campo($request, 'categoria');
        $codigos = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', $this->campo($request, 'codigos')) ?: []))));
        if (!isset(CATEGORIAS[$cat]) || !$codigos || count($codigos) > 5000) {
            flash('error', 'Elige una categoría y pega al menos un código (máximo 5000).');
            return;
        }
        $n = Usuario::cargarLista($cat, $codigos);
        Auditoria::registrar('categoria_lista_cargada', 'usuario', null, ['categoria' => $cat, 'codigos' => count($codigos), 'actualizados' => $n]);
        flash('ok', "$n estudiantes pasaron a " . CATEGORIA_UNO[$cat] . '. ' . (count($codigos) - $n ? (count($codigos) - $n) . ' códigos no cambiaron (no existen o ya estaban en esa categoría).' : ''));
    }

    private function guardar(Request $request, string $rol): void
    {
        $id = (int)$this->campo($request, 'id');
        $activo = $request->has('activo') ? 1 : 0;
        $cat = $this->campo($request, 'categoria');
        $cat = isset(CATEGORIAS[$cat]) ? $cat : 'regular';
        $pass = $this->campo($request, 'password');
        $ant = Usuario::buscar($id);
        if (!$ant || !isset(ROLES[$rol])) {
            flash('error', 'Usuario o rol no válido.');
        } elseif ($id === user()['id'] && ($rol !== 'admin' || !$activo)) {
            flash('error', 'No puedes quitarte tu propio rol de administrador ni desactivarte.');
        } elseif ($pass !== '' && strlen($pass) < 8) {
            flash('error', 'La nueva contraseña debe tener al menos 8 caracteres.');
        } else {
            Usuario::actualizar($id, $rol, $activo, $cat);
            $cambios = [];
            if ($ant['rol'] !== $rol) $cambios['rol'] = [$ant['rol'], $rol];
            if ((int)$ant['activo'] !== $activo) $cambios['activo'] = [(int)$ant['activo'], $activo];
            if ($ant['categoria'] !== $cat) $cambios['categoria'] = [$ant['categoria'], $cat];
            if ($pass !== '') {
                Usuario::cambiarPassword($id, $pass);
                $cambios['password'] = 'restablecida';
            }
            if ($cambios) Auditoria::registrar('usuario_modificado', 'usuario', $id, ['codigo' => $ant['codigo']] + $cambios);
            flash('ok', 'Cambios guardados.');
        }
    }
}
