export default function ProductCardSkeleton() {
    return (
        <div className="flex flex-col overflow-hidden rounded-xl bg-white shadow-sm">
            <div className="aspect-square w-full animate-pulse bg-gray-200" />
            <div className="flex flex-1 flex-col gap-2 p-2 sm:p-3">
                <div className="h-2.5 w-4/5 animate-pulse rounded-full bg-gray-200" />
                <div className="h-2.5 w-1/2 animate-pulse rounded-full bg-gray-200" />
                <div className="mt-1 flex items-center justify-between">
                    <div className="h-3 w-12 animate-pulse rounded-full bg-gray-200" />
                    <div className="h-7 w-7 animate-pulse sm:h-8 sm:w-8 rounded-full bg-gray-200" />
                </div>
            </div>
        </div>
    );
}
