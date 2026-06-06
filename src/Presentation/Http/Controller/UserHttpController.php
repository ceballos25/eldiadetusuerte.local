<?php
declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Infrastructure\Repository\PdoAdminRepository;
use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Presentation\Http\Middleware\PermissionMiddleware;
use App\Shared\Audit\AuditLogger;
use App\Shared\Exception\DomainException;
use App\Shared\Http\Request;
use App\Shared\Http\Response;
use Throwable;

final class UserHttpController
{
    private const ACTIONS = ['listar', 'roles', 'crear', 'actualizar'];

    private const PERMS = [
        'listar' => ['usuarios.view'],
        'roles' => ['usuarios.view'],
        'crear' => ['usuarios.manage'],
        'actualizar' => ['usuarios.manage'],
    ];

    public function __construct(
        private readonly PdoAdminRepository $admins,
        private readonly PermissionMiddleware $permissions,
        private readonly CsrfMiddleware $csrf,
        private readonly AuditLogger $audit
    ) {
    }

    public function __invoke(Request $request): never
    {
        try {
            $action = trim((string)$request->input('action', ''));
            if (!in_array($action, self::ACTIONS, true)) {
                Response::json(['success' => false, 'message' => 'Acción no válida'], 422);
            }

            $this->permissions->authorize($action, self::PERMS);
            $this->csrf->handle($request, ['crear', 'actualizar']);

            $result = match ($action) {
                'listar' => ['success' => true, 'data' => $this->admins->findAll()],
                'roles' => ['success' => true, 'data' => $this->admins->findRoles()],
                'crear' => $this->crear($request),
                'actualizar' => $this->actualizar($request),
                default => ['success' => false],
            };

            $this->audit->log('usuarios.' . $action, ['success' => (bool)($result['success'] ?? true)]);
            Response::json($result);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function crear(Request $request): array
    {
        $email = trim((string)$request->input('email_admin', ''));
        $pass = trim((string)$request->input('password_admin', ''));
        $roleId = (int)$request->input('id_role', 0);

        if ($email === '' || $pass === '') {
            throw new DomainException('Email y contraseña son obligatorios');
        }
        if ($this->admins->emailExists($email)) {
            throw new DomainException('El email ya está registrado');
        }

        $roles = $this->admins->findRoles();
        $slug = '';
        foreach ($roles as $r) {
            if ((int)$r['id_role'] === $roleId) {
                $slug = (string)$r['slug_role'];
                break;
            }
        }

        $id = $this->admins->create($email, $pass, $roleId, $slug);

        return ['success' => true, 'id_admin' => $id, 'message' => 'Usuario creado'];
    }

    private function actualizar(Request $request): array
    {
        $id = (int)$request->input('id_admin', 0);
        if ($id <= 0) {
            throw new DomainException('ID inválido');
        }

        $email = trim((string)$request->input('email_admin', ''));
        if ($email !== '' && $this->admins->emailExists($email, $id)) {
            throw new DomainException('El email ya está en uso');
        }

        $data = array_filter([
            'email_admin' => $email !== '' ? $email : null,
            'password_admin' => trim((string)$request->input('password_admin', '')),
            'id_role' => $request->input('id_role') !== null ? (int)$request->input('id_role') : null,
            'status_admin' => $request->input('status_admin') !== null ? (int)$request->input('status_admin') : null,
        ], static fn ($v) => $v !== null && $v !== '');

        if (isset($data['id_role'])) {
            foreach ($this->admins->findRoles() as $r) {
                if ((int)$r['id_role'] === (int)$data['id_role']) {
                    $data['rol_admin'] = $r['slug_role'];
                    break;
                }
            }
        }

        $this->admins->update($id, $data);

        return ['success' => true, 'message' => 'Usuario actualizado'];
    }
}
