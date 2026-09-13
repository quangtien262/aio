<?php

namespace App\Core\Modules\Support;

use InvalidArgumentException;

readonly class ModuleSecurityDefinition
{
    /**
     * @param  array<string, array{name:string,description:?string,risk_level:string}>  $permissions
     * @param  array<string, array{name:string,description:?string,permissions:list<string>}>  $roles
     * @param  array<string, array<string, mixed>>  $criticalPolicies
     * @param  array<string, array<string, mixed>>  $features
     */
    public function __construct(
        public string $version,
        public array $permissions,
        public array $roles,
        public array $criticalPolicies = [],
        public array $features = [],
        public string $customRolePolicy = 'allow',
        public string $assignmentChannel = 'core',
    ) {
        if (! in_array($this->customRolePolicy, ['allow', 'deny'], true)) {
            throw new InvalidArgumentException('Unsupported custom role policy.');
        }
        if (! preg_match('/\A[a-z][a-z0-9_-]{1,39}\z/', $this->assignmentChannel)) {
            throw new InvalidArgumentException('Unsupported module role assignment channel.');
        }

        foreach ($this->roles as $roleKey => $role) {
            $unknown = array_diff($role['permissions'], array_keys($this->permissions));

            if ($unknown !== []) {
                throw new InvalidArgumentException(sprintf(
                    'Role [%s] references unknown permissions: %s.',
                    $roleKey,
                    implode(', ', $unknown),
                ));
            }
        }
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        return array_keys($this->permissions);
    }

    /** @return array<string, mixed> */
    public function fingerprintPayload(): array
    {
        return [
            'version' => $this->version,
            'permissions' => $this->permissions,
            'roles' => $this->roles,
            'critical_policies' => $this->criticalPolicies,
            'features' => $this->features,
            'custom_role_policy' => $this->customRolePolicy,
            'assignment_channel' => $this->assignmentChannel,
        ];
    }

    public function roleDefinitionHash(string $roleKey): string
    {
        if (! isset($this->roles[$roleKey])) {
            throw new InvalidArgumentException("Unknown module role [{$roleKey}].");
        }

        $permissionKeys = array_values(array_unique($this->roles[$roleKey]['permissions']));
        sort($permissionKeys);

        return hash('sha256', json_encode([
            'module_version' => $this->version,
            'role_key' => $roleKey,
            'permission_keys' => $permissionKeys,
            'critical_policies' => $this->criticalPolicies,
            'features' => $this->features,
            'custom_role_policy' => $this->customRolePolicy,
            'assignment_channel' => $this->assignmentChannel,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
