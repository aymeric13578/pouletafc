@extends('merchand.components.layout')

@section('main')
<!-- Edit Company modal -->
<div class="modal fade" id="editShopModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier Catégorie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editShopForm">
                    @csrf
                  <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="category_name">Nom boutique</label>
                        <input type="text" class="form-control" id="shop_name" name="shop_name" placeholder="Saisissez le nom de la boutique" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="shop_code">Code boutique</label>
                        <input type="text" class="form-control" id="shop_code" name="shop_code" placeholder="Saisissez le code de la boutique" />
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="shop_brand">Marque boutique</label>
                        <input type="text" class="form-control" id="shop_brand" name="shop_brand" placeholder="Saisissez la marque de la boutique" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="shop_telephone1">Numéro téléphone</label>
                        <input type="text" class="form-control" id="shop_telephone1" name="shop_telephone1" placeholder="Saisissez le numéro de téléphone de la boutique" />
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="shop_address">Adresse boutique</label>
                        <input type="text" class="form-control" id="shop_address" name="shop_address" placeholder="Saisissez l'adresse de la boutique" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="shop_email1">E-mail boutique</label>
                        <input type="text" class="form-control" id="shop_email1" name="shop_email1" placeholder="Saisissez l'email de la boutique" />
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="shop_commercial_register">Numéro registre de commerce</label>
                        <input type="text" class="form-control" id="shop_commercial_register" name="shop_commercial_register" placeholder="Saisissez le numéro de registre de commerce" />
                    </div>

                  </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary"
                    data-bs-dismiss="modal">Fermer</button>
                <button id="editShopSaveButton" type="button"
                    class="btn btn-primary">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">eCommerce /</span> Liste des boutiques
</h4>

<!-- Product List Widget -->

<!-- <div class="card mb-4">
  <div class="card-widget-separator-wrapper">
    <div class="card-body card-widget-separator">
      <div class="row gy-4 gy-sm-1">
        <div class="col-sm-6 col-lg-3">
          <div class="d-flex justify-content-between align-items-start card-widget-1 border-end pb-3 pb-sm-0">
            <div>
              <h6 class="mb-2">In-store Sales</h6>
              <h4 class="mb-2">FCFA 0</h4>
              <p class="mb-0"><span class="text-muted me-2">0 orders</span><span class="badge bg-label-success">+5.7%</span></p>
            </div>
            <span class="avatar me-sm-4">
              <span class="avatar-initial bg-label-secondary rounded"><i class="ti-md ti ti-smart-home text-body"></i></span>
            </span>
          </div>
          <hr class="d-none d-sm-block d-lg-none me-4">
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="d-flex justify-content-between align-items-start card-widget-2 border-end pb-3 pb-sm-0">
            <div>
              <h6 class="mb-2">Website Sales</h6>
              <h4 class="mb-2">FCFA 0</h4>
              <p class="mb-0"><span class="text-muted me-2">0 orders</span><span class="badge bg-label-success">+0%</span></p>
            </div>
            <span class="avatar p-2 me-lg-4">
              <span class="avatar-initial bg-label-secondary rounded"><i class="ti-md ti ti-device-laptop text-body"></i></span>
            </span>
          </div>
          <hr class="d-none d-sm-block d-lg-none">
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="d-flex justify-content-between align-items-start border-end pb-3 pb-sm-0 card-widget-3">
            <div>
              <h6 class="mb-2">Discount</h6>
              <h4 class="mb-2">FCFA 0</h4>
              <p class="mb-0 text-muted">0 orders</p>
            </div>
            <span class="avatar p-2 me-sm-4">
              <span class="avatar-initial bg-label-secondary rounded"><i class="ti-md ti ti-gift text-body"></i></span>
            </span>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="mb-2">Affiliate</h6>
              <h4 class="mb-2">FCFA 0</h4>
              <p class="mb-0"><span class="text-muted me-2">0 orders</span><span class="badge bg-label-danger">-0%</span></p>
            </div>
            <span class="avatar p-2">
              <span class="avatar-initial bg-label-secondary rounded"><i class="ti-md ti ti-wallet text-body"></i></span>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div> -->

<!-- Product List Table -->
@if (session()->has('message'))
        <div class="alert alert-success">
            {{ session()->get('message') }}
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
          <th>Nom boutique</th>
          <th>Code boutique</th>
          <th>Numéro de téléphone</th>
          <th>Adresse de la boutique</th>
          <th>E-mail de la boutique</th>
          <th>Numéro de registre de commerce</th>
          <th>Statut</th>

          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <@foreach ($shops as $item)
        <tr>

               <td>{{ $item->{'shop_name'} }}</td>
               <td>{{ $item->{'ref'} }}</td>
               <td>{{ $item->{'phone1'} }}</td>
               <td>{{ $item->{'address'} }}</td>
               <td>{{ $item->{'email1'} }}</td>
               <td>{{ $item->{'commercial_register'} }}</td>
             
  
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
               <a class="edit-shop" about="{{ $item->{'id'} }}" href="javascript:void(0);" title="Modifier boutique"><i class="ti ti-pencil me-1 text-primary"></i></a>
               @if($item->status == "pending" || $item->status == null)
               <a href="{{route('status.change',['id'=>$item->id, 'base'=>'shops' , 'status'=>'Success' ])}}"    class='badge bg-success' >Activer</a>
               @endif
               @if($item->status == "Success")
               <a href="{{route('status.change',['id'=>$item->id, 'base'=>'shops' , 'status'=>'Pending' ])}}"    class='badge bg-danger' >Desactiver</a>
               @endif


              
              
              </td>



             </tr>
          @endforeach>
    </tbody>
    </table>
  </div>
</div>


          </div>
          <!-- / Content -->

@endsection
@section('page-script')
<script type="text/javascript">

    let shopEditionId;
    $(document).ready(function (){

        //Click on .edit-cat
      $("body").on("click", ".edit-shop", function (event) {
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

      //Click on editCatSaveButton
      $("#editShopSaveButton").on("click", function (event) {
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

        parameters["_token"] = "{{ csrf_token() }}",
        //Loader.setLoading($this);
        $.ajax({
          url: "{{ route('shopedition') }}",
          type: "post",
          dataType: "json",
          data: parameters,
          success: function (data) {

            if (data.code === 100) {
              editShopModal.hide();
              setTimeout(function(){
                //datatableInstance.ajax.reload();
                window.parent.location.reload();
              }, 2000);
              toastr.success("Boutique modifiée avec succès");
            } else {
              //Error occurred
              toastr.error("Une erreur est survenue durant l'opération!");
            }
            //Loader.removeLoading($this);
          },
          error: function () {
            toastr.error("Une erreur est survenue durant l'opération!");
            //Loader.removeLoading($this);
          }
        });
      });

      //Click on .delete-cat
      $("body").on("click", ".delete-shop", async function (event) {
        event.preventDefault();
        let shopDesignation = $(this).parent().parent().children().first().html();
        let id = $(this).attr("about");

        let $this = $(this);
        if (confirm(`Voulez-vous réellement supprimer la boutique ${shopDesignation} ?`)) {
          //Loader.setLoading($this);
          $.ajax({
            url: "{{ route('shopdeletion') }}",
            data: {
              id: id,
              _token: "{{ csrf_token() }}"
            },
            type: "post",
            dataType: "json",
            success: function (data) {
              if (data.code === 100) {
                toastr.success("Boutique supprimée avec succès!");
                setTimeout(function(){
                    //datatableInstance.ajax.reload();
                    window.parent.location.reload();
                  }, 2000);
              } else {
                toastr.error("Une erreur est survenue durant l'opération!");
              }
              //Loader.removeLoading($this);
            },
            error: function () {
              toastr.error("Une erreur est survenue durant l'opération!");
              //Loader.removeLoading($this);
            },
          });
        }
      });

      datatableInstance = $("#shop-table").DataTable({
        "processing": true,
        "language": {
          "search": '',
          "searchPlaceholder": "Rechercher..."
        },
      });
    });
</script>
@endsection

