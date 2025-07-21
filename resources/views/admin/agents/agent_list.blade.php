@extends('admin.components.layout')

@section('main')
<!-- Edit agent modal -->
<div class="modal fade" id="editAgentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editAgentForm" action="{{ route('agentedition') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="id" id="agent_id">
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="agent_name">Nom complet agent <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="agent_name" name="agent_name" placeholder="Saisissez le nom complet de l'agent" required />
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="phone">Numéro téléphone <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="Saisissez le numéro de téléphone de l'agent" required />
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-12">
                            <label class="form-label" for="national_identity_card_number">Numéro pièce d'identité</label>
                            <input type="text" class="form-control" id="national_identity_card_number" name="national_identity_card_number" placeholder="Saisissez le numéro de la pièce d'identité" />
                        </div>
                     
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="photo">Photo de l'agent</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*" />
                            <small class="text-muted">Laissez vide pour ne pas modifier.</small>
                            <div id="photo-preview" class="mt-2"></div>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="identity_card_file">Carte d'identité (fichier)</label>
                            <input type="file" class="form-control" id="identity_card_file" name="identity_card_file" />
                            <small class="text-muted">Laissez vide pour ne pas modifier.</small>
                            <div id="identity-card-preview" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="location_plan_file">Plan de localisation (fichier)</label>
                            <input type="file" class="form-control" id="location_plan_file" name="location_plan_file" />
                            <small class="text-muted">Laissez vide pour ne pas modifier.</small>
                            <div id="location-plan-preview" class="mt-2"></div>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="type">Type d'agent</label>
                            <input type="text" class="form-control" id="type" name="type" placeholder="Saisissez le type d'agent" />
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="vehicule">Véhicule</label>
                            <select class="form-control" id="vehicule" name="vehicule">
                                <option value="">Sélectionnez un véhicule</option>
                                <option value="moto">Moto</option>
                                <option value="voiture">Voiture</option>
                            </select>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="matricule_vehicule">Matricule véhicule</label>
                            <input type="text" class="form-control" id="matricule_vehicule" name="matricule_vehicule" placeholder="Saisissez le matricule du véhicule" />
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="latitude">Latitude</label>
                            <input type="text" class="form-control" id="latitude" name="latitude" placeholder="Saisissez la latitude" />
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="longitude">Longitude</label>
                            <input type="text" class="form-control" id="longitude" name="longitude" placeholder="Saisissez la longitude" />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Fermer</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Credit agent modal -->
<div class="modal fade" id="creditAgentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Créditer agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="creditAgentForm" action="{{ route('agent.credit') }}" method="POST">
                    @csrf
                    <input type="hidden" name="id_user" id="agent_id_user">
                    <div class="row mb-3">
                        <div class="col-lg-12">
                            <label class="form-label" for="amount">Montant à créditer <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="amount" name="amount" placeholder="Saisissez le montant" required />
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-12">
                            <label class="form-label" for="password">Mot de passe <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Saisissez votre mot de passe" required />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Fermer</button>
                        <button type="submit" class="btn btn-primary">Créditer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">eCommerce /</span> Liste des agents
</h4>

<!-- Product List Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Liste</h5>
        <!-- Afficher la notification ici -->
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session()->get('message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session()->get('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        <div class="d-flex justify-content-between align-items-center row py-3 gap-3 gap-md-0">
            <div class="col-md-4 product_status"></div>
            <div class="col-md-4 product_category"></div>
            <div class="col-md-4 product_stock"></div>
        </div>
    </div>
    <div class="card-datatable table-responsive text-nowrap">
        <table id="agent-table" class="datatables table">
            <thead class="border-top">
                <tr>
                    <th>Référence</th>
                    <th>Nom complet agent</th>
                    <th>Numéro de téléphone</th>
                    <th>Total Crédité</th>
                    <th>Solde</th>
                    <th>Total Gagné</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($agents as $item)
                <tr>
                    <td>{{ $item->ref }}</td>
                    <td>{{ $item->agent_name }}</td>
                    <td>{{ $item->phone }}</td>
                    <td>{{ number_format($item->total_credited, 0, ',', ' ') }} FCFA</td>
                    <td>{{ number_format($item->balance, 0, ',', ' ') }} FCFA</td>
                    <td>{{ number_format($item->total_earned, 0, ',', ' ') }} FCFA</td>
                    <td>
                        @if ($item->status == "Success")
                            <span class="badge bg-success">Actif</span>
                        @elseif ($item->status == "pending")
                            <span class="badge bg-info">En attente</span>
                        @else
                            <span class="badge bg-danger">Suspendu</span>
                        @endif
                    </td>
                    <td>
                        <a class="edit-agent" 
                           data-id="{{ $item->id }}"
                           data-agent_name="{{ $item->agent_name }}"
                           data-phone="{{ $item->phone }}"
                           data-national_identity_card_number="{{ $item->national_identity_card_number }}"
                           data-registration_number="{{ $item->registration_number }}"
                           data-latitude="{{ $item->latitude }}"
                           data-longitude="{{ $item->longitude }}"
                           data-type="{{ $item->type }}"
                           data-vehicule="{{ $item->vehicule }}"
                           data-matricule_vehicule="{{ $item->matricule_vehicule }}"
                           data-photo="{{ $item->photo }}"
                           data-identity_card_file="{{ $item->identity_card_file }}"
                           data-location_plan_file="{{ $item->location_plan_file }}"
                           href="javascript:void(0);" title="Modifier agent"><i class="ti ti-pencil me-1 text-primary"></i></a>
                        <a class="credit-agent" about="{{ $item->id_user }}" href="javascript:void(0);" title="Créditer agent"><i class="ti ti-wallet me-1 text-success"></i></a>
                        <a class="view-credit-history" href="{{ route('agent.credit.history', $item->id_user) }}" title="Historique des crédits"><i class="ti ti-history me-1 text-info"></i></a>
                        @if($item->status == "pending" || $item->status == null)
                            <a href="{{ route('status.change', ['id' => $item->id, 'base' => 'agents', 'status' => 'Success']) }}" class="badge bg-success">Activer</a>
                        @endif
                        @if($item->status == "Success")
                            <a href="{{ route('status.change', ['id' => $item->id, 'base' => 'agents', 'status' => 'Pending']) }}" class="badge bg-danger">Désactiver</a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('page-script')
<script type="text/javascript">
    $(document).ready(function () {
        // Vérifier si jQuery et Bootstrap sont chargés
        if (typeof jQuery === 'undefined') {
            console.error('jQuery n\'est pas chargé !');
        }
        if (typeof bootstrap === 'undefined') {
            console.error('Bootstrap n\'est pas chargé !');
        }

        // Click on .edit-agent
        $("body").on("click", ".edit-agent", function (event) {
            event.preventDefault();
            console.log('Clic sur Modifier agent');

            let agent = $(this).data();

            $("#agent_id").val(agent.id || '');
            $("#agent_name").val(agent.agent_name || '');
            $("#phone").val(agent.phone || '');
            $("#national_identity_card_number").val(agent.national_identity_card_number || '');
            $("#registration_number").val(agent.registration_number || '');
            $("#latitude").val(agent.latitude || '');
            $("#longitude").val(agent.longitude || '');
            $("#type").val(agent.type || '');
            $("#vehicule").val(agent.vehicule || '');
            $("#matricule_vehicule").val(agent.matricule_vehicule || '');

            // Afficher les fichiers existants
            if (agent.photo) {
                $("#photo-preview").html('<img src="' + agent.photo + '" alt="Photo de l\'agent" style="max-width: 100px; max-height: 100px;" />');
            } else {
                $("#photo-preview").html('');
            }

            if (agent.identity_card_file) {
                $("#identity-card-preview").html('<a href="' + agent.identity_card_file + '" target="_blank">Voir la carte d\'identité</a>');
            } else {
                $("#identity-card-preview").html('');
            }

            if (agent.location_plan_file) {
                $("#location-plan-preview").html('<a href="' + agent.location_plan_file + '" target="_blank">Voir le plan de localisation</a>');
            } else {
                $("#location-plan-preview").html('');
            }

            try {
                let editAgentModal = new bootstrap.Modal(document.getElementById("editAgentModal"), {
                    "backdrop": "static",
                    "keyboard": false,
                });
                editAgentModal.show();
                console.log('Modal Modifier agent ouvert');
            } catch (e) {
                console.error('Erreur lors de l\'ouverture du modal Modifier :', e);
            }
        });

        // Click on .credit-agent
        $("body").on("click", ".credit-agent", function (event) {
            event.preventDefault();
            console.log('Clic sur Créditer agent');

            let id_user = $(this).attr("about");
            $("#agent_id_user").val(id_user);
            $("#amount").val('');
            $("#password").val('');

            try {
                let creditAgentModal = new bootstrap.Modal(document.getElementById("creditAgentModal"), {
                    "backdrop": "static",
                    "keyboard": false,
                });
                creditAgentModal.show();
                console.log('Modal Créditer agent ouvert');
            } catch (e) {
                console.error('Erreur lors de l\'ouverture du modal Créditer :', e);
            }
        });

        // Gérer la fermeture manuelle des modals
        $('#editAgentModal').on('hidden.bs.modal', function () {
            $("#agent_id").val('');
            $("#agent_name").val('');
            $("#phone").val('');
            $("#national_identity_card_number").val('');
            $("#registration_number").val('');
            $("#latitude").val('');
            $("#longitude").val('');
            $("#type").val('');
            $("#vehicule").val('');
            $("#matricule_vehicule").val('');
            $("#photo").val('');
            $("#identity_card_file").val('');
            $("#location_plan_file").val('');
            $("#photo-preview").html('');
            $("#identity-card-preview").html('');
            $("#location-plan-preview").html('');
        });

        $('#creditAgentModal').on('hidden.bs.modal', function () {
            $("#agent_id_user").val('');
            $("#amount").val('');
            $("#password").val('');
        });

        // Initialize DataTable for agent table
        try {
            $("#agent-table").DataTable({
                "processing": true,
                "pageLength": 10,
                "lengthMenu": [10, 25, 50, 100],
                "language": {
                    "search": '',
                    "searchPlaceholder": "Rechercher..."
                },
                "paging": true,
                "serverSide": false,
            });
            console.log('DataTable initialisé avec succès');
        } catch (e) {
            console.error('Erreur lors de l\'initialisation de DataTable :', e);
        }
    });
</script>
@endsection