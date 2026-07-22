export default function ProductCardSkeleton() {
    return (
        <div className="flex flex-col overflow-hidden rounded-2xl bg-white shadow-md">
            <div className="aspect-square w-full animate-pulse bg-gray-200" />
            <div className="flex flex-1 flex-col gap-3 p-4 sm:p-5">
                <div className="h-3.5 w-4/5 animate-pulse rounded-full bg-gray-200" />
                <div className="h-3.5 w-1/2 animate-pulse rounded-full bg-gray-200" />
                <div className="mt-1 flex items-center justify-between">
                    <div className="h-4 w-16 animate-pulse rounded-full bg-gray-200" />
                    <div className="h-10 w-10 animate-pulse rounded-full bg-gray-200" />
                </div>
            </div>
        </div>
    );
}
