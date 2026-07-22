export default function QuantityStepper({ value, min = 1, max = 99, onChange, size = 'md' }) {
    const dec = () => onChange(Math.max(min, value - 1));
    const inc = () => onChange(Math.min(max, value + 1));

    const padding = size === 'sm' ? 'h-8 w-8 text-sm' : 'h-10 w-10';

    return (
        <div className="inline-flex items-center rounded-full border border-gray-200 bg-white shadow-sm">
            <button
                type="button"
                onClick={dec}
                disabled={value <= min}
                className={`${padding} flex items-center justify-center rounded-full text-gray-500 transition-all duration-300 hover:bg-gray-100 hover:text-brand-700 active:scale-90 disabled:opacity-30 disabled:active:scale-100`}
                aria-label="Diminuer la quantité"
            >
                –
            </button>
            <span className="w-8 text-center text-sm font-semibold text-gray-900">{value}</span>
            <button
                type="button"
                onClick={inc}
                disabled={value >= max}
                className={`${padding} flex items-center justify-center rounded-full text-gray-500 transition-all duration-300 hover:bg-gray-100 hover:text-brand-700 active:scale-90 disabled:opacity-30 disabled:active:scale-100`}
                aria-label="Augmenter la quantité"
            >
                +
            </button>
        </div>
    );
}
