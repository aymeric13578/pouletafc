import ClientLayout from '@/Layouts/ClientLayout';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import { Head } from '@inertiajs/react';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <ClientLayout title="Mon profil">
            <Head title="Mon profil" />

            <div className="space-y-6">
                <div className="animate-fade-in-up rounded-2xl bg-white p-4 shadow-md sm:p-8">
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                        className="max-w-xl"
                    />
                </div>

                <div style={{ animationDelay: '60ms' }} className="animate-fade-in-up rounded-2xl bg-white p-4 shadow-md sm:p-8">
                    <UpdatePasswordForm className="max-w-xl" />
                </div>

                <div style={{ animationDelay: '120ms' }} className="animate-fade-in-up rounded-2xl bg-white p-4 shadow-md sm:p-8">
                    <DeleteUserForm className="max-w-xl" />
                </div>
            </div>
        </ClientLayout>
    );
}
