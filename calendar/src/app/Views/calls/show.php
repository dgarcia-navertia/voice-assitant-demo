<?php
$roleLabel = ['user' => 'Cliente', 'assistant' => 'Asistente'];
?>
<div class="max-w-3xl">
    <a href="/calls" class="text-sm text-gray-500 hover:text-gray-700">← Volver a llamadas</a>
    <h1 class="text-2xl font-semibold text-gray-900 mt-2 mb-1">Transcripción</h1>
    <p class="text-sm text-gray-500 mb-6 tabular-nums">
        <?php if ($call): ?>
        <?= htmlspecialchars((string) ($call['to_number'] ?? $call['from_number'] ?? '')) ?> ·
        <?= date('d/m/Y H:i', strtotime($call['created_at'])) ?> · <?= htmlspecialchars($call['status']) ?> ·
        <?php endif; ?>
        <span class="text-gray-400"><?= htmlspecialchars($sid) ?></span>
    </p>

    <?php if (empty($turns)): ?>
    <div class="card text-center py-12 text-gray-500" data-testid="transcript-empty">
        <p class="text-sm">Esta llamada todavía no tiene transcripción.</p>
    </div>
    <?php else: ?>
    <div class="space-y-3" data-testid="transcript">
        <?php foreach ($turns as $t): ?>
        <?php $isUser = $t['role'] === 'user'; ?>
        <div class="flex <?= $isUser ? 'justify-end' : 'justify-start' ?>">
            <div class="<?= $isUser ? 'bg-primary text-white' : 'glass-strong' ?> max-w-[85%] rounded-3xl px-5 py-3">
                <div class="text-xs mb-1 <?= $isUser ? 'text-white/70' : 'text-gray-500' ?>">
                    <?= $roleLabel[$t['role']] ?? $t['role'] ?><?= $t['interrupted'] ? ' · interrumpido' : '' ?>
                </div>
                <div class="whitespace-pre-wrap"><?= htmlspecialchars($t['transcript_text']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
