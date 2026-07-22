const STATUS_MAP = {
    pending: { label: 'En cours', classes: 'bg-amber-100 text-amber-800' },
    process: { label: 'En préparation', classes: 'bg-amber-100 text-amber-800' },
    take: { label: 'En livraison', classes: 'bg-blue-100 text-blue-800' },
    Success: { label: 'Livrée', classes: 'bg-green-100 text-green-800' },
    declin: { label: 'Annulée', classes: 'bg-red-100 text-red-800' },
    failed: { label: 'Annulée', classes: 'bg-red-100 text-red-800' },
};

export default function StatusBadge({ status }) {
    const info = STATUS_MAP[status] ?? { label: status ?? 'Inconnu', classes: 'bg-gray-100 text-gray-700' };

    return (
        <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${info.classes}`}>
            {info.label}
        </span>
    );
}
