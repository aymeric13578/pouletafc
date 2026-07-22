import { Link } from '@inertiajs/react';

export default function Pagination({ meta }) {
    if (!meta || meta.last_page <= 1) {
        return null;
    }

    return (
        <nav className="mt-8 flex flex-wrap items-center justify-center gap-1">
            {meta.links.map((link, index) => (
                <Link
                    key={index}
                    href={link.url ?? '#'}
                    preserveScroll
                    className={`min-w-[2.25rem] rounded-lg px-3 py-2 text-center text-sm font-medium transition-all duration-300 ${
                        link.active
                            ? 'bg-brand-600 text-white shadow-sm'
                            : link.url
                              ? 'text-gray-600 hover:bg-gray-100 active:scale-95'
                              : 'cursor-not-allowed text-gray-300'
                    }`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </nav>
    );
}
