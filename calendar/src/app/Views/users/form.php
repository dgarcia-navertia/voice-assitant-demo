<div class="w-full">
    <div class="mb-6">
        <a href="/users" class="text-sm text-gray-500 hover:text-gray-700">← Volver a usuarios</a>
        <h1 class="text-xl font-semibold text-gray-900 mt-2">
            <?= $targetUser ? 'Editar usuario' : 'Nuevo usuario' ?>
        </h1>
    </div>

    <div class="card p-6">
        <form method="POST" action="<?= $action ?>" class="space-y-4">
            <?php if ($method !== 'POST'): ?>
            <input type="hidden" name="_method" value="<?= $method ?>">
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1">
                <?php foreach ($errors as $msg): ?>
                <div><?= htmlspecialchars($msg) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" name="name" required
                           value="<?= htmlspecialchars($targetUser['name'] ?? $_POST['name'] ?? '') ?>"
                           class="input">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($targetUser['email'] ?? $_POST['email'] ?? '') ?>"
                           class="input">
                </div>
            </div>

            <?php if ($targetUser && (int) $targetUser['id'] === \App\Auth::id()): ?>
            <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-500">
                La contraseña de tu propia cuenta se cambia desde
                <a href="/account" class="text-brand-700 hover:underline">Mi cuenta</a>, no aquí.
            </div>
            <?php else: ?>
            <div class="md:w-1/2 md:pr-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Contraseña<?= $targetUser ? ' (dejar en blanco para no cambiarla)' : '' ?>
                </label>
                <input type="password" name="password" <?= $targetUser ? '' : 'required' ?>
                       autocomplete="new-password"
                       class="input">
                <p class="text-xs text-gray-400 mt-1"><?= \App\PasswordPolicy::help() ?></p>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
                    <?php $currentRole = $targetUser['role'] ?? $_POST['role'] ?? 'commercial'; ?>
                    <select name="role" id="role" onchange="onRoleChange()"
                            class="input">
                        <option value="commercial" <?= $currentRole === 'commercial' ? 'selected' : '' ?>>Comercial</option>
                        <option value="manager" <?= $currentRole === 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="admin" <?= $currentRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">
                        Este rol es solo de login. Para que la persona sea reservable (reciba citas), dala de alta o
                        edítala también en <a href="/commercials" class="text-brand-700 hover:underline">Comerciales</a>.
                    </p>
                </div>

                <div id="store-field" class="<?= $currentRole === 'manager' ? '' : 'hidden' ?>">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tienda</label>
                    <select name="managed_store_id" id="managed_store_id"
                            class="input">
                        <option value="">Seleccionar tienda</option>
                        <?php foreach ($stores as $store): ?>
                        <option value="<?= $store['id'] ?>"
                            <?= ($targetUser['managed_store_id'] ?? $_POST['managed_store_id'] ?? '') == $store['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($store['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Solo aplica al rol Manager: limita su acceso a esa tienda.</p>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="btn btn-primary">
                    <?= $targetUser ? 'Guardar cambios' : 'Crear usuario' ?>
                </button>
                <a href="/users" class="btn btn-secondary">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function onRoleChange() {
    const isManager = document.getElementById('role').value === 'manager';
    const field = document.getElementById('store-field');
    field.classList.toggle('hidden', !isManager);
}
</script>
