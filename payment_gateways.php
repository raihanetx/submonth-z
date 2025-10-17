<?php
// This file is included in admin.php
// It provides the UI for managing payment gateways.

// The $pdo variable is available from admin.php
$payment_gateways = $pdo->query("SELECT * FROM payment_gateways ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div x-data="{ editingGateway: null, newGateway: {name: '', number: '', is_crypto: false} }">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Manage Payment Gateways</h2>

    <!-- Add New Gateway Form -->
    <div class="bg-white p-6 rounded-lg border shadow-sm mb-8">
        <h3 class="text-xl font-semibold text-gray-700 mb-4" x-text="editingGateway ? 'Edit Gateway' : 'Add New Gateway'">Add New Gateway</h3>
        <form :action="'api.php?action=' + (editingGateway ? 'edit_gateway' : 'add_gateway')" method="POST" enctype="multipart/form-data" class="space-y-4" @submit.prevent="editingGateway ? null : handleAddGateway($event)">
            <input type="hidden" name="action" :value="editingGateway ? 'edit_gateway' : 'add_gateway'">
            <input type="hidden" name="gateway_id" x-model="editingGateway.id">

            <div>
                <label class="block mb-1.5 font-medium text-gray-700 text-sm">Gateway Name</label>
                <input type="text" name="name" class="form-input" placeholder="e.g., bKash" required x-model="editingGateway ? editingGateway.name : newGateway.name">
            </div>
            <div>
                <label class="block mb-1.5 font-medium text-gray-700 text-sm">Number / Pay ID</label>
                <input type="text" name="number" class="form-input" placeholder="e.g., 01234567890" required x-model="editingGateway ? editingGateway.number : newGateway.number">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_crypto" value="1" class="h-4 w-4 rounded border-gray-300 text-[var(--primary-color)] focus:ring-[var(--primary-color)]" x-model="editingGateway ? editingGateway.is_crypto : newGateway.is_crypto">
                <label class="text-sm font-medium text-gray-700">Is this a cryptocurrency? (e.g., Binance Pay)</label>
            </div>
            <div>
                <label class="block mb-1.5 font-medium text-gray-700 text-sm">Logo</label>
                <input type="file" name="logo" class="form-input" accept="image/*">
                <p class="text-xs text-gray-500 mt-1" x-show="editingGateway">Leave blank to keep the current logo.</p>
            </div>
            <div class="flex justify-end gap-4 mt-4">
                <button type="button" class="btn btn-secondary" x-show="editingGateway" @click="editingGateway = null">Cancel Edit</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk mr-2"></i>
                    <span x-text="editingGateway ? 'Save Changes' : 'Add Gateway'"></span>
                </button>
            </div>
        </form>
    </div>

    <!-- Existing Gateways List -->
    <div class="bg-white p-6 rounded-lg border shadow-sm">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Existing Gateways</h3>
        <div class="space-y-4">
            <?php if (empty($payment_gateways)): ?>
                <p class="text-gray-500 text-center py-8">No payment gateways have been added yet.</p>
            <?php else: ?>
                <?php foreach ($payment_gateways as $gateway): ?>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border flex-wrap gap-4">
                    <div class="flex items-center gap-4 flex-grow">
                        <img src="<?= htmlspecialchars($gateway['logo_url'] ?: 'https://via.placeholder.com/64/E9D5FF/5B21B6?text=N/A') ?>" class="w-16 h-16 object-contain rounded-md bg-white p-1 border">
                        <div>
                            <p class="font-bold text-gray-800 text-lg"><?= htmlspecialchars($gateway['name']) ?></p>
                            <p class="text-sm text-gray-600 font-mono"><?= htmlspecialchars($gateway['number']) ?></p>
                            <?php if ($gateway['is_crypto']): ?>
                                <span class="mt-1 inline-block bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-0.5 rounded-full">Crypto</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <button class="btn btn-secondary btn-sm" @click="editingGateway = <?= htmlspecialchars(json_encode($gateway)) ?>">
                            <i class="fa-solid fa-pencil"></i> Edit
                        </button>
                        <form action="api.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this payment gateway?');" class="inline-block">
                            <input type="hidden" name="action" value="delete_gateway">
                            <input type="hidden" name="gateway_id" value="<?= $gateway['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function handleAddGateway(event) {
        // This is a placeholder for potential client-side validation or handling
        // For now, it just submits the form.
        event.target.submit();
    }
</script>