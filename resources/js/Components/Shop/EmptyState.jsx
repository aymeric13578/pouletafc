import { Link } from '@inertiajs/react';

export default function EmptyState({ title, description, actionLabel, actionHref }) {
    return (
        <div className="flex animate-fade-in-up flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-3xl animate-fade-in">
                🍗
            </div>
            <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
            {description && <p className="mt-2 max-w-md text-sm text-gray-500">{description}</p>}
            {actionLabel && actionHref && (
                <Link
                    href={actionHref}
                    className="mt-6 inline-flex items-center rounded-full bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-300 hover:bg-brand-700 hover:shadow-md active:scale-95"
                >
                    {actionLabel}
                </Link>
            )}
        </div>
    );
}
