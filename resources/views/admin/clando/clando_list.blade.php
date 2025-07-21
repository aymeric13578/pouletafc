@extends('admin.components.layout')

@section('main')
    <!-- Details Clando Modal -->
    <div class="modal fade" id="detailsClandoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails de la course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label">Référence</label>
                            <p id="clando_ref"></p>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Client</label>
                            <p id="clando_client"></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label">Contact</label>
                            <p id="clando_contact"></p>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Adresse de destination</label>
                            <p id="clando_destination"></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label">Position de départ (Lat, Lon)</label>
                            <p id="clando_my_position"></p>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Position de l'agent (Lat, Lon)</label>
                            <p id="clando_agent_position"></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label">Destination (Lat, Lon)</label>
                            <p id="clando_destination_position"></p>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Statut</label>
                            <p id="clando_status"></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label">Prix</label>
                            <p id="clando_price"></p>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Distance</label>
                            <p id="clando_distance"></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label">Temps</label>
                            <p id="clando_times"></p>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Type</label>
                            <p id="clando_type"></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label">Véhicule</label>
                            <p id="clando_vehicule"></p>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Matricule du véhicule</label>
                            <p id="clando_matricule"></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label">Type de livraison</label>
                            <p id="clando_delivery_type"></p>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Commission agent</label>
                            <p id="clando_commission_agent"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">eCommerce /</span> Liste des courses
    </h4>

    <!-- Clando List Table -->
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
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Liste</h5>
            <div class="d-flex justify-content-between align-items-center row py-3 gap-3 gap-md-0">
                <div class="col-md-4 product_status"></div>
                <div class="col-md-4 product_category"></div>
                <div class="col-md-4 product_stock"></div>
            </div>
        </div>
        <div class="card-datatable table-responsive text-nowrap">
            <table id="clando-table" class="datatables table">
                <thead class="border-top">
                    <tr>
                        <th>Ref</th>
                        <th>Destination</th>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clandos as $item)
                        <tr>
                            <td>{{ $item->ref }}</td>
                            <td>{{ $item->destinationName }}</td>
                            <td>{{ $item->users->name ?? 'N/A' }}</td>
                            <td>{{ $item->users->phone ?? 'N/A' }}</td>
                            <td>
                                @if ($item->status == 'Success')
                                    <span class="badge bg-success">Course terminée</span>
                                @elseif ($item->status == 'pending')
                                    <span class="badge bg-info">En cours de préparation</span>
                                @elseif ($item->status == 'process' || $item->status == 'take')
                                    <span class="badge bg-warning">En cours de dépôt</span>
                                @elseif ($item->status == 'want')
                                    <span class="badge bg-danger">En attente d'un agent</span>
                                @elseif ($item->status == 'declin')
                                    <span class="badge bg-danger">Annulé</span>
                                @else
                                    <span class="badge bg-secondary">Inconnu</span>
                                @endif
                            </td>
                            <td>
                                <a class="view-clando" 
                                   data-id="{{ $item->id }}"
                                   data-ref="{{ $item->ref }}"
                                   data-client="{{ $item->users->name ?? 'N/A' }}"
                                   data-contact="{{ $item->users->phone ?? 'N/A' }}"
                                   data-destination="{{ $item->destinationName }}"
                                   data-my_position="{{ $item->latMyPosition . ', ' . $item->lonMyPosition }}"
                                   data-agent_position="{{ $item->latAgent . ', ' . $item->lonAgent }}"
                                   data-destination_position="{{ $item->latDestination . ', ' . $item->lonDestination }}"
                                   data-status="{{ $item->status }}"
                                   data-price="{{ number_format($item->price, 0, ',', ' ') }} FCFA"
                                   data-distance="{{ $item->distance }}"
                                   data-times="{{ $item->times }}"
                                   data-type="{{ $item->type }}"
                                   data-vehicule="{{ $item->vehicule }}"
                                   data-matricule="{{ $item->matricule_vehicule }}"
                                   data-delivery_type="{{ $item->delivery_type }}"
                                   data-commission_agent="{{ number_format($item->commission_agent, 0, ',', ' ') }} FCFA"
                                   href="javascript:void(0);" title="Voir les détails"><i class="ti ti-eye me-1 text-primary"></i></a>
                                <!-- Changer le statut -->
                                @if ($item->status != 'want')
                                    <a href="{{ route('clando.status.change', ['id' => $item->id, 'status' => 'want']) }}"
                                       class="badge bg-danger">En attente</a>
                                @endif
                                @if ($item->status != 'take')
                                    <a href="{{ route('clando.status.change', ['id' => $item->id, 'status' => 'take']) }}"
                                       class="badge bg-warning">Pris en charge</a>
                                @endif
                                @if ($item->status != 'process')
                                    <a href="{{ route('clando.status.change', ['id' => $item->id, 'status' => 'process']) }}"
                                       class="badge bg-warning">En cours</a>
                                @endif
                                @if ($item->status != 'declin')
                                    <a href="{{ route('clando.status.change', ['id' => $item->id, 'status' => 'declin']) }}"
                                       class="badge bg-danger">Annuler</a>
                                @endif
                                @if ($item->status != 'Success')
                                    <a href="{{ route('clando.status.change', ['id' => $item->id, 'status' => 'Success']) }}"
                                       class="badge bg-success">Terminer</a>
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

            // Click on .view-clando
            $("body").on("click", ".view-clando", function (event) {
                event.preventDefault();
                console.log('Clic sur Voir les détails');

                let clando = $(this).data();

                $("#clando_ref").text(clando.ref || 'N/A');
                $("#clando_client").text(clando.client || 'N/A');
                $("#clando_contact").text(clando.contact || 'N/A');
                $("#clando_destination").text(clando.destination || 'N/A');
                $("#clando_my_position").text(clando.my_position || 'N/A');
                $("#clando_agent_position").text(clando.agent_position || 'N/A');
                $("#clando_destination_position").text(clando.destination_position || 'N/A');
                $("#clando_status").text(clando.status || 'N/A');
                $("#clando_price").text(clando.price || 'N/A');
                $("#clando_distance").text(clando.distance || 'N/A');
                $("#clando_times").text(clando.times || 'N/A');
                $("#clando_type").text(clando.type || 'N/A');
                $("#clando_vehicule").text(clando.vehicule || 'N/A');
                $("#clando_matricule").text(clando.matricule || 'N/A');
                $("#clando_delivery_type").text(clando.delivery_type || 'N/A');
                $("#clando_commission_agent").text(clando.commission_agent || 'N/A');

                try {
                    let detailsClandoModal = new bootstrap.Modal(document.getElementById("detailsClandoModal"), {
                        "backdrop": "static",
                        "keyboard": false,
                    });
                    detailsClandoModal.show();
                    console.log('Modal Détails de la course ouvert');
                } catch (e) {
                    console.error('Erreur lors de l\'ouverture du modal Détails :', e);
                }
            });

            // Gérer la fermeture manuelle du modal
            $('#detailsClandoModal').on('hidden.bs.modal', function () {
                $("#clando_ref").text('');
                $("#clando_client").text('');
                $("#clando_contact").text('');
                $("#clando_destination").text('');
                $("#clando_my_position").text('');
                $("#clando_agent_position").text('');
                $("#clando_destination_position").text('');
                $("#clando_status").text('');
                $("#clando_price").text('');
                $("#clando_distance").text('');
                $("#clando_times").text('');
                $("#clando_type").text('');
                $("#clando_vehicule").text('');
                $("#clando_matricule").text('');
                $("#clando_delivery_type").text('');
                $("#clando_commission_agent").text('');
            });

            // Initialize DataTable for clando table
            try {
                $("#clando-table").DataTable({
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