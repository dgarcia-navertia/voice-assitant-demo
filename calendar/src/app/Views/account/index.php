<?php use App\PasswordPolicy; ?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-gray-900">Mi cuenta</h1>
    <p class="text-sm text-gray-500 mt-1">Tus datos de acceso a la agenda.</p>
</div>

<?php if (!empty($success)): ?>
<div class="mb-6 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
    Contraseña actualizada correctamente.
</div>
<?php endif; ?>

<div class="max-w-lg space-y-6">
    <div class="card p-6">
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-gray-500">Nombre</dt>
                <dd class="text-gray-900 font-medium"><?= htmlspecialchars($user['name'] ?? '') ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-gray-500">Email</dt>
                <dd class="text-gray-900"><?= htmlspecialchars($user['email'] ?? '') ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-gray-500">Rol</dt>
                <?php $roles = ['admin' => 'Admin', 'manager' => 'Manager', 'commercial' => 'Comercial']; ?>
                <dd class="text-gray-900"><?= htmlspecialchars($roles[$user['role'] ?? ''] ?? ($user['role'] ?? '')) ?></dd>
            </div>
        </dl>
    </div>

    <div class="card p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-4">Cambiar contraseña</h2>

        <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/account/password" class="space-y-4">
            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña actual</label>
                <input type="password" id="current_password" name="current_password" required
                       autocomplete="current-password"
                       class="input">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña nueva</label>
                <input type="password" id="password" name="password" required
                       autocomplete="new-password"
                       minlength="<?= PasswordPolicy::MIN_LENGTH ?>" maxlength="<?= PasswordPolicy::MAX_LENGTH ?>"
                       class="input">
                <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars(PasswordPolicy::help()) ?></p>
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Repite la contraseña nueva</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                       autocomplete="new-password"
                       minlength="<?= PasswordPolicy::MIN_LENGTH ?>" maxlength="<?= PasswordPolicy::MAX_LENGTH ?>"
                       class="input">
            </div>
            <div class="pt-2">
                <button type="submit"
                        class="btn btn-primary">
                    Guardar contraseña
                </button>
            </div>
        </form>
    </div>
</div>
