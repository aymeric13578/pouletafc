@extends('admin.components.layout')

@section('main')
    <!-- Modal pour activer les notifications sonores -->
    <div class="modal fade" id="enableSoundModal" tabindex="-1" aria-labelledby="enableSoundModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="enableSoundModalLabel">Activer les notifications sonores</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Cliquez sur "Activer" pour permettre la lecture des sons de notification pour les nouvelles commandes.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="enableSoundButton">Activer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Conteneur pour les notifications Bootstrap -->
    <div id="notificationContainer" class="position-fixed top-0 start-50 translate-middle-x" style="z-index: 1060; width: 50%;"></div>

    <!-- Product List Table -->
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session()->get('message') }}
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
            <table id="shop-table" class="datatables table">
                <thead class="border-top">
                    <tr>
                        <th>Ref</th>
                        <th>Acknowledgment</th>
                        <th>Adress</th>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $item)
                        <tr>
                            <td>{{ $item->{'ref'} }}</td>
                            <td></td>
                            <td>{{ $item->{'address'} }}</td>
                            <td>{{ $item->user->{'name'} }}</td>
                            <td>{{ $item->user->{'phone'} }}</td>
                            <td>
                                @if ($item->status == 'Success')
                                    <span class="badge bg-success">Livraison Terminée</span>
                                @elseif ($item->status == 'pending')
                                    <span class="badge bg-info">En cours de préparation</span>
                                @elseif($item->status == 'process' || $item->status == 'take')
                                    <span class="badge bg-warning">En cours de livraison</span>
                                @elseif($item->status == 'want')
                                    <span class="badge bg-danger">En attente d'un agent</span>
                                @else
                                    <span class="badge bg-secondary">Inconnu</span>
                                @endif
                            </td>
                            <td>
                                <a class="edit-shop" about="{{ $item->{'id'} }}" href="javascript:void(0);"
                                    title="Modifier boutique"><i class="ti ti-eye me-1 text-primary"></i></a>
                                <a href="{{ route('status.change', ['id' => $item->id, 'base' => 'shops', 'status' => 'failed']) }}"
                                    class=''><i class="ti ti-trash me-1 text-danger"></i></a>
                                @if ($item->status == 'pending')
                                    <a href="{{ route('status.change', ['id' => $item->id, 'base' => 'order_details', 'status' => 'want']) }}"
                                        class='badge bg-success'>Colis prêt</a>
                                @endif
                                @if ($item->status == 'Success')
                                    <a href="{{ route('status.change', ['id' => $item->id, 'base' => 'shops', 'status' => 'Pending']) }}"
                                        class='badge bg-danger'>Désactiver</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Audio element for notification sound -->
    <audio id="notificationSound" src="{{ asset('sounds/notification.mp3') }}" preload="auto"></audio>
@endsection

@section('page-script')
    <style>
        /* Assurer que le tableau s'adapte à la largeur de l'écran */
        #shop-table {
            width: 100% !important;
            table-layout: auto; /* Permet aux colonnes de s'ajuster automatiquement */
        }

        /* Réduire le padding et la taille de police pour les cellules */
        #shop-table th,
        #shop-table td {
            padding: 8px 5px; /* Réduire le padding */
            font-size: 0.85rem; /* Taille de police plus petite */
            white-space: nowrap; /* Éviter le retour à la ligne */
            overflow: hidden;
            text-overflow: ellipsis; /* Ajouter des points de suspension si le texte est trop long */
        }

        /* Ajuster la largeur des colonnes spécifiques */
        #shop-table th:nth-child(1),
        #shop-table td:nth-child(1) {
            width: 10%; /* Ref */
        }
        #shop-table th:nth-child(2),
        #shop-table td:nth-child(2) {
            width: 12%; /* Acknowledgment */
        }
        #shop-table th:nth-child(3),
        #shop-table td:nth-child(3) {
            width: 20%; /* Adress */
        }
        #shop-table th:nth-child(4),
        #shop-table td:nth-child(4) {
            width: 15%; /* Client */
        }
        #shop-table th:nth-child(5),
        #shop-table td:nth-child(5) {
            width: 15%; /* Contact */
        }
        #shop-table th:nth-child(6),
        #shop-table td:nth-child(6) {
            width: 15%; /* Statut */
        }
        #shop-table th:nth-child(7),
        #shop-table td:nth-child(7) {
            width: 13%; /* Actions */
        }

        /* Réduire la taille des badges et boutons pour gagner de l'espace */
        .badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }

        /* Masquer les éléments moins prioritaires sur petits écrans */
        @media (max-width: 768px) {
            #shop-table th,
            #shop-table td {
                font-size: 0.75rem; /* Encore plus petit sur mobile */
            }

            /* Masquer la colonne "Contact" sur petits écrans */
            #shop-table th:nth-child(5),
            #shop-table td:nth-child(5) {
                display: none;
            }

            /* Ajuster les largeurs pour petits écrans */
            #shop-table th:nth-child(1),
            #shop-table td:nth-child(1) {
                width: 15%;
            }
            #shop-table th:nth-child(2),
            #shop-table td:nth-child(2) {
                width: 15%;
            }
            #shop-table th:nth-child(3),
            #shop-table td:nth-child(3) {
                width: 25%;
            }
            #shop-table th:nth-child(4),
            #shop-table td:nth-child(4) {
                width: 20%;
            }
            #shop-table th:nth-child(6),
            #shop-table td:nth-child(6) {
                width: 15%;
            }
            #shop-table th:nth-child(7),
            #shop-table td:nth-child(7) {
                width: 10%;
            }
        }

        /* Assurer que le conteneur du tableau ne dépasse pas */
        .card-datatable {
            overflow-x: hidden; /* Pas de défilement horizontal */
        }

        /* Animation clignotante */
        .blink {
            animation: blink-animation 1s steps(5, start) infinite;
            background-color: #ffeb3b !important;
        }

        @keyframes blink-animation {
            to {
                background-color: #fff176 !important;
            }
        }

        .custom-notification {
            background-color: #28a745;
            color: white;
            font-weight: bold;
            border-radius: 5px;
            padding: 15px;
            margin-top: 70px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.5s ease-in-out;
            z-index: 1060;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .acknowledge-btn {
            margin-left: 5px;
            cursor: pointer;
            font-size: 0.7rem;
        }
    </style>

    <script type="text/javascript">
        let shopEditionId;
        let lastOrderId = 0;
        let isSoundEnabled = false;
        let isProcessingNotification = false;

        $(document).ready(function() {
            // Récupérer l'ID de la dernière commande au chargement de la page
            @if($orders->count() > 0)
                lastOrderId = {{ $orders->first()->id }};
            @endif

            // Vérifier si le son est déjà activé dans la session
            if (sessionStorage.getItem('soundEnabled') === 'true') {
                isSoundEnabled = true;
                let audio = document.getElementById('notificationSound');
                audio.muted = true;
                let playPromise = audio.play();
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        audio.muted = false;
                        console.log('Son autorisé via sessionStorage');
                    }).catch(error => {
                        console.warn('Son non autorisé malgré sessionStorage, réinitialisation:', error);
                        isSoundEnabled = false;
                        sessionStorage.removeItem('soundEnabled');
                        let soundModal = new bootstrap.Modal(document.getElementById('enableSoundModal'), {
                            backdrop: 'static',
                            keyboard: false
                        });
                        soundModal.show();
                    });
                }
            } else {
                let soundModal = new bootstrap.Modal(document.getElementById('enableSoundModal'), {
                    backdrop: 'static',
                    keyboard: false
                });
                soundModal.show();
            }

            // Activer le son lorsque l'utilisateur clique sur le bouton
            $('#enableSoundButton').on('click', function() {
                let audio = document.getElementById('notificationSound');
                let playPromise = audio.play();
                if (playPromise !== undefined) {
                    playPromise.then(function() {
                        isSoundEnabled = true;
                        sessionStorage.setItem('soundEnabled', 'true');
                        let soundModal = bootstrap.Modal.getInstance(document.getElementById('enableSoundModal'));
                        soundModal.hide();
                        console.log('Son activé avec succès');
                    }).catch(function(error) {
                        console.error('Erreur lors de l\'activation du son:', error);
                        alert('Impossible d\'activer le son. Veuillez vérifier les paramètres de votre navigateur ou autoriser la lecture automatique dans les paramètres du site.');
                    });
                }
            });

            // Fermer la modale sans activer le son
            $('.btn-close').on('click', function() {
                let soundModal = bootstrap.Modal.getInstance(document.getElementById('enableSoundModal'));
                soundModal.hide();
                isSoundEnabled = false;
                sessionStorage.removeItem('soundEnabled');
                console.log('Modale fermée, son désactivé');
            });

            // Initialiser DataTable avec responsive
            let datatableInstance = $("#shop-table").DataTable({
                "processing": true,
                "language": {
                    "search": '',
                    "searchPlaceholder": "Rechercher..."
                },
                "order": [[0, "desc"]], // Tri initial par Ref (colonne 0) décroissant
                "responsive": true, // Activer le mode responsive
                "autoWidth": false, // Désactiver l'ajustement automatique de la largeur
                "columnDefs": [
                    { "width": "10%", "targets": 0 }, // Ref
                    { "width": "12%", "targets": 1 }, // Acknowledgment
                    { "width": "20%", "targets": 2 }, // Adress
                    { "width": "15%", "targets": 3 }, // Client
                    { "width": "15%", "targets": 4 }, // Contact
                    { "width": "15%", "targets": 5 }, // Statut
                    { "width": "13%", "targets": 6 }  // Actions
                ]
            });

            // Fonction pour afficher une notification Bootstrap
            function showNotification(message) {
                let notification = `
                    <div class="alert alert-success custom-notification alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                $('#notificationContainer').append(notification);
                setTimeout(function() {
                    $('#notificationContainer .alert').first().alert('close');
                }, 5000);
            }

            // Fonction pour jouer le son de notification
            function playNotificationSound() {
                if (!isSoundEnabled) {
                    console.warn('Son désactivé : l\'utilisateur n\'a pas encore activé les notifications sonores.');
                    return;
                }
                let audio = document.getElementById('notificationSound');
                if (audio.readyState >= 2) {
                    let playPromise = audio.play();
                    if (playPromise !== undefined) {
                        playPromise.catch(function(error) {
                            console.error('Erreur lors de la lecture du son:', error);
                            if (error.name === 'NotAllowedError') {
                                isSoundEnabled = false;
                                sessionStorage.removeItem('soundEnabled');
                                let soundModal = new bootstrap.Modal(document.getElementById('enableSoundModal'), {
                                    backdrop: 'static',
                                    keyboard: false
                                });
                                soundModal.show();
                            }
                        });
                    }
                } else {
                    console.error('Fichier audio non chargé. État:', audio.readyState);
                }
            }

            // Fonction pour ajouter une nouvelle commande à la table
            function addNewOrderToTable(order) {
                let statusBadge;
                if (order.status === 'Success') {
                    statusBadge = '<span class="badge bg-success">Livraison Terminée</span>';
                } else if (order.status === 'pending') {
                    statusBadge = '<span class="badge bg-info">En cours de préparation</span>';
                } else if (order.status === 'process' || order.status === 'take') {
                    statusBadge = '<span class="badge bg-warning">En cours de livraison</span>';
                } else if (order.status === 'want') {
                    statusBadge = '<span class="badge bg-danger">En attente d\'un agent</span>';
                } else {
                    statusBadge = '<span class="badge bg-secondary">Inconnu</span>';
                }

                let acknowledgeButton = `<button class="acknowledge-btn badge bg-primary" data-order-id="${order.id}">J'ai vu</button>`;

                let actions = `
                    <a class="edit-shop" about="${order.id}" href="javascript:void(0);" title="Modifier boutique"><i class="ti ti-eye me-1 text-primary"></i></a>
                    <a href="{{ route('status.change', ['id' => ':id', 'base' => 'shops', 'status' => 'failed']) }}".replace(':id', order.id) class=''><i class="ti ti-trash me-1 text-danger"></i></a>
                `;
                if (order.status === 'pending') {
                    actions += `<a href="{{ route('status.change', ['id' => ':id', 'base' => 'order_details', 'status' => 'want']) }}".replace(':id', order.id) class='badge bg-success'>Colis prêt</a>`;
                }
                if (order.status === 'Success') {
                    actions += `<a href="{{ route('status.change', ['id' => ':id', 'base' => 'shops', 'status' => 'Pending']) }}".replace(':id', order.id) class='badge bg-danger'>Désactiver</a>`;
                }

                let newRow = datatableInstance.row.add([
                    order.ref,
                    acknowledgeButton,
                    order.address,
                    order.user_name,
                    order.user_phone,
                    statusBadge,
                    actions
                ]).node();

                $(newRow).addClass('blink');
                $(newRow).prependTo('#shop-table tbody');
                datatableInstance.draw(false);
            }

            // Gestion du clic sur le bouton "J'ai vu"
            $("body").on("click", ".acknowledge-btn", function() {
                let row = $(this).closest('tr');
                $(row).removeClass('blink');
                $(this).remove();
                console.log('Animation et bouton supprimés pour la commande:', $(this).data('order-id'));
            });

            // Vérifier les nouvelles commandes toutes les 6 secondes
            setInterval(function() {
                if (isProcessingNotification) return;
                isProcessingNotification = true;

                $.ajax({
                    url: "{{ route('check.new.order') }}",
                    type: "GET",
                    data: { lastOrderId: lastOrderId },
                    dataType: "json",
                    success: function(data) {
                        if (data.hasNewOrder) {
                            lastOrderId = data.newOrderId;
                            playNotificationSound();
                            showNotification('Nouvelle commande disponible !');

                            $.ajax({
                                url: "{{ route('get.new.order') }}",
                                type: "GET",
                                data: { orderId: data.newOrderId },
                                dataType: "json",
                                success: function(response) {
                                    if (response.success) {
                                        addNewOrderToTable(response.order);
                                    }
                                },
                                error: function() {
                                    console.error("Erreur lors de la récupération des détails de la commande.");
                                },
                                complete: function() {
                                    isProcessingNotification = false;
                                }
                            });
                        } else {
                            isProcessingNotification = false;
                        }
                    },
                    error: function() {
                        console.error("Erreur lors de la vérification des nouvelles commandes.");
                        isProcessingNotification = false;
                    }
                });
            }, 6000);

            // Gestion des clics pour modifier une boutique
            $("body").on("click", ".edit-shop", function(event) {
                event.preventDefault();

                let id = $(this).attr("about");
                let designation = $(this).parent().parent().children().first().html();
                let code = $(this).parent().parent().children(":eq(1)").html();
                let telephone1 = $(this).parent().parent().children(":eq(2)").html();
                let address = $(this).parent().parent().children(":eq(3)").html();
                let email1 = $(this).parent().parent().children(":eq(4)").html();
                let brand = $(this).parent().parent().children(":eq(5)").html();
                let commercial_register = $(this).parent().parent().children(":eq(6)").html();

                $("#shop_name").val(designation);
                $("#shop_code").val(code);
                $("#shop_brand").val(brand);
                $("#shop_telephone1").val(telephone1);
                $("#shop_address").val(address);
                $("#shop_email1").val(email1);
                $("#shop_commercial_register").val(commercial_register);

                editShopModal = new bootstrap.Modal(document.getElementById("editShopModal"), {
                    "backdrop": "static",
                    "keyboard": false,
                });
                editShopModal.show();
                shopEditionId = id;
            });

            // Gestion de la sauvegarde des modifications
            $("#editShopSaveButton").on("click", function(event) {
                event.preventDefault();
                let $this = $(this);
                let parameters = {
                    id: shopEditionId
                };
                let designation = $("#shop_name").val();
                if (designation === "") {
                    toastr.warning("Veuillez renseigner le champ nom de la boutique");
                    return;
                }
                parameters["designation"] = designation;

                let code = $("#shop_code").val();
                parameters["code"] = code;

                let telephone1 = $("#shop_telephone1").val();
                parameters["telephone1"] = telephone1;

                let address = $("#shop_address").val();
                parameters["address"] = address;

                let email1 = $("#shop_email1").val();
                parameters["email1"] = email1;

                let brand = $("#shop_brand").val();
                parameters["brand"] = brand;

                let commercial_register = $("#shop_commercial_register").val();
                parameters["commercial_register"] = commercial_register;

                parameters["_token"] = "{{ csrf_token() }}";
                $.ajax({
                    url: "{{ route('shopedition') }}",
                    type: "post",
                    dataType: "json",
                    data: parameters,
                    success: function(data) {
                        if (data.code === 100) {
                            editShopModal.hide();
                            setTimeout(function() {
                                window.parent.location.reload();
                            }, 2000);
                            toastr.success("Boutique modifiée avec succès");
                        } else {
                            toastr.error("Une erreur est survenue durant l'opération!");
                        }
                    },
                    error: function() {
                        toastr.error("Une erreur est survenue durant l'opération!");
                    }
                });
            });

            // Gestion de la suppression
            $("body").on("click", ".delete-shop", async function(event) {
                event.preventDefault();
                let shopDesignation = $(this).parent().parent().children().first().html();
                let id = $(this).attr("about");

                let $this = $(this);
                if (confirm(`Voulez-vous réellement supprimer la boutique ${shopDesignation} ?`)) {
                    $.ajax({
                        url: "{{ route('shopdeletion') }}",
                        data: {
                            id: id,
                            _token: "{{ csrf_token() }}"
                        },
                        type: "post",
                        dataType: "json",
                        success: function(data) {
                            if (data.code === 100) {
                                toastr.success("Boutique supprimée avec succès!");
                                setTimeout(function() {
                                    window.parent.location.reload();
                                }, 2000);
                            } else {
                                toastr.error("Une erreur est survenue durant l'opération!");
                            }
                        },
                        error: function() {
                            toastr.error("Une erreur est survenue durant l'opération!");
                        },
                    });
                }
            });
        });
    </script>
@endsection