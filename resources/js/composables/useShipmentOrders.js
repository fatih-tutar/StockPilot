import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

export function useShipmentOrders() {
    const createOpen = ref(false);
    const createOptions = ref(null);
    const createError = ref('');
    const editingShipment = ref(null);
    const deletingShipment = ref(null);
    const deleteProcessing = ref(false);

    const openCreate = async () => {
        editingShipment.value = null;
        createOpen.value = true;
        createError.value = '';

        if (createOptions.value?.vehicles && !createOptions.value.shipment) {
            return;
        }

        createOptions.value = null;

        try {
            const { data } = await axios.get(route('shipments.create-data'));
            createOptions.value = data;
        } catch {
            createError.value = 'Form yüklenemedi.';
        }
    };

    const openEdit = async (shipmentId) => {
        editingShipment.value = null;
        createOpen.value = true;
        createError.value = '';
        createOptions.value = null;

        try {
            const { data } = await axios.get(route('shipments.editor', shipmentId));
            createOptions.value = data;
            editingShipment.value = data.shipment;
        } catch {
            createError.value = 'Form yüklenemedi.';
        }
    };

    const closeCreate = () => {
        createOpen.value = false;
    };

    const setStatus = (shipment, status) => {
        router.patch(
            route('shipments.status', shipment.id),
            { status },
            { preserveScroll: true },
        );
    };

    const askDelete = (shipment) => {
        deletingShipment.value = shipment;
    };

    const closeDelete = () => {
        if (deleteProcessing.value) {
            return;
        }

        deletingShipment.value = null;
    };

    const confirmDelete = () => {
        if (!deletingShipment.value) {
            return;
        }

        deleteProcessing.value = true;

        router.delete(route('shipments.destroy', deletingShipment.value.id), {
            preserveScroll: true,
            onFinish: () => {
                deleteProcessing.value = false;
                deletingShipment.value = null;
            },
        });
    };

    const runAction = (shipment, key) => {
        if (key === 'edit') {
            openEdit(shipment.id);
            return;
        }

        if (key === 'print') {
            window.open(route('shipments.print', shipment.id), '_blank');
            return;
        }

        if (key === 'prepare') {
            setStatus(shipment, 'preparing');
            return;
        }

        if (key === 'invoice') {
            setStatus(shipment, 'invoiced');
            return;
        }

        if (key === 'archive') {
            setStatus(shipment, 'archived');
            return;
        }

        if (key === 'revert') {
            setStatus(shipment, shipment.status === 'invoiced' ? 'preparing' : 'received');
            return;
        }

        if (key === 'delete') {
            askDelete(shipment);
        }
    };

    return {
        createOpen,
        createOptions,
        createError,
        editingShipment,
        deletingShipment,
        deleteProcessing,
        openCreate,
        openEdit,
        closeCreate,
        askDelete,
        closeDelete,
        confirmDelete,
        runAction,
    };
}
