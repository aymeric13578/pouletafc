export function formatPrice(amount) {
    const value = Number(amount) || 0;

    return `${new Intl.NumberFormat('fr-FR').format(value)} FCFA`;
}
