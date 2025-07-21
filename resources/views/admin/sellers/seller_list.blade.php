@extends('admin.components.layout')

@section('main')
<!-- Edit Company modal -->
<div class="modal fade" id="editSellerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier Marchand</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editSellerForm">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="seller_full_name">Nom complet marchand</label>
                            <input type="text" class="form-control" id="seller_full_name" name="seller_full_name" placeholder="Saisissez le nom complet du marchand" />
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="seller_code">Code marchand</label>
                            <input type="text" class="form-control" id="seller_code" name="seller_code" placeholder="Saisissez le code du marchand" />
                        </div>
                      </div>
                      <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="seller_telephone1">Numéro téléphone 1</label>
                            <input type="text" class="form-control" id="seller_telephone1" name="seller_telephone1" placeholder="Saisissez le numéro de téléphone 1" />
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="seller_telephone2">Numéro téléphone 2</label>
                            <input type="text" class="form-control" id="seller_telephone2" name="seller_telephone2" placeholder="Saisissez le numéro de téléphone 2" />
                        </div>
                      </div>
                      <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="seller_telephone3">Numéro téléphone 3</label>
                            <input type="text" class="form-control" id="seller_telephone3" name="seller_telephone3" placeholder="Saisissez le numéro de téléphone 3" />
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="seller_address">Adresse boutique</label>
                            <input type="text" class="form-control" id="seller_address" name="seller_address" placeholder="Saisissez l'adresse du marchand" />
                        </div>
                      </div>
                      <div class="row mb-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="seller_email1">E-mail marchand 1</label>
                            <input type="text" class="form-control" id="seller_email1" name="seller_email1" placeholder="Saisissez l'email 1 du marchand" />
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="seller_email2">E-mail marchand 2</label>
                            <input type="text" class="form-control" id="seller_email2" name="seller_email2" placeholder="Saisissez l'email 2 du marchand" />
                        </div>

                      </div>
                      <div class="row mb-3">
                        <div class="col-lg-6">
                            <label for="shop_id" class="form-label">Nom boutique <span class="text-danger">*</span></label>
                            <select class="form-select" id="shop_id" name="shop_id">
                                <option selected>Sélectionnez une une boutique</option>
                                @foreach ($shops as $shop)
                                    <option value="{{ $shop->{'id'} }}">{{ $shop->{'shop_name'} }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="seller_niu">NIU Marchand</label>
                            <input type="text" class="form-control" id="seller_niu" name="seller_niu" placeholder="Saisissez le niu du marchand" />
                        </div>
                      </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary"
                    data-bs-dismiss="modal">Fermer</button>
                <button id="editSellerSaveButton" type="button"
                    class="btn btn-primary">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">eCommerce /</span> Liste des marchands
</h4>

<!-- Marchand List Widget -->

<div class="card mb-4">
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
</div>

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
    <table id="seller-table" class="datatables table">
      <thead class="border-top">
        <tr>
          <th>Nom complet marchand</th>
          <th>Code marchand</th>
          <th>Numéro de téléphone 1</th>
          <th>Numéro de téléphone 2</th>
          <th>Numéro de téléphone 3</th>
          <th>Adresse / Localité</th>
          <th>E-mail marchand 1</th>
          <th>E-mail marchand 2</th>
          <th>NIU Marchand</th>
          <th>Nom boutique</th>
          <th>Photo marchand</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <@foreach ($sellers as $item)
        <tr>

               <td>{{ $item->{'seller_full_name'} }}</td>
               <td>{{ $item->{'seller_code'} }}</td>
               <td>{{ $item->{'seller_telephone1'} }}</td>
               <td>{{ $item->{'seller_telephone2'} }}</td>
               <td>{{ $item->{'seller_telephone3'} }}</td>
               <td>{{ $item->{'seller_address'} }}</td>
               <td>{{ $item->{'seller_email1'} }}</td>
               <td>{{ $item->{'seller_email2'} }}</td>
               <td>{{ $item->{'seller_niu'} }}</td>
               <td>{{ $item->{'shop'} != null ? $item->{'shop'}->{'shop_name'} : '' }}</td>
               <td>
                <img style="height: 50px;" src="{{ asset($item->{'seller_photo'}) }}" alt="">
               </td>
               <td>
                    <a class="edit-seller" about="{{ $item->{'id'} }}" href="javascript:void(0);" title="Modifier marchand"><i class="ti ti-pencil me-1 text-primary"></i></a>
                    <a class="delete-seller" about="{{ $item->{'id'} }}" href="javascript:void(0);" title="Supprimer marchand"><i class="ti ti-trash me-1 text-danger"></i></a>
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

    let sellerEditionId;
    $(document).ready(function (){

        //Click on .edit-cat
      $("body").on("click", ".edit-seller", function (event) {
        event.preventDefault();

        let id = $(this).attr("about");
        let name = $(this).parent().parent().children().first().html();
        let code = $(this).parent().parent().children(":eq(1)").html();
        let phone1 = $(this).parent().parent().children(":eq(2)").html();
        let phone2 = $(this).parent().parent().children(":eq(3)").html();
        let phone3 = $(this).parent().parent().children(":eq(4)").html();
        let address = $(this).parent().parent().children(":eq(5)").html();
        let email1 = $(this).parent().parent().children(":eq(6)").html();
        let email2 = $(this).parent().parent().children(":eq(7)").html();
        let niu = $(this).parent().parent().children(":eq(8)").html();
        let shop_id = $(this).parent().parent().children(":eq(9)").html();

        $("#seller_full_name").val(name);
        $("#seller_code").val(code);
        $("#seller_telephone1").val(phone1);
        $("#seller_telephone2").val(phone2);
        $("#seller_telephone3").val(phone3);
        $("#seller_address").val(address);
        $("#seller_email1").val(email1);
        $("#seller_email2").val(email2);
        $("#seller_niu").val(niu);
        $("#shop_id").val(shop_id);
        $("#shop_id").trigger('change');

        editSellerModal = new bootstrap.Modal(document.getElementById("editSellerModal"), {
          "backdrop": "static",
          "keyboard": false,
        });
        editSellerModal.show();
        sellerEditionId = id;
      });

      //Click on editCatSaveButton
      $("#editSellerSaveButton").on("click", function (event) {
        event.preventDefault();
        let $this = $(this);
        let parameters = {
          id: sellerEditionId
        };
        let name = $("#seller_full_name").val();
        if (name === "") {
          toastr.warning("Veuillez renseigner le champ nom du marchand");
          return;
        }
        parameters["name"] = name;

        let code = $("#seller_code").val();
        parameters["seller_code"] = code;

        let telephone1 = $("#seller_telephone1").val();
        parameters["seller_telephone1"] = telephone1;

        let telephone2 = $("#seller_telephone2").val();
        parameters["seller_telephone2"] = telephone2;

        let telephone3 = $("#seller_telephone3").val();
        parameters["seller_telephone3"] = telephone3;

        let address = $("#seller_address").val();
        parameters["seller_address"] = address;

        let email1 = $("#seller_email1").val();
        parameters["seller_email1"] = email1;

        let email2 = $("#seller_email2").val();
        parameters["seller_email2"] = email2;

        let niu = $("#seller_niu").val();
        parameters["seller_niu"] = niu;

        let shop = $("#shop_id").val();
        if (shop === "") {
            toastr.warning("Veuillez renseigner le champ nom de la boutique");
            return;
          }
        parameters["shop_id"] = shop;

        parameters["_token"] = "{{ csrf_token() }}",
        //Loader.setLoading($this);
        $.ajax({
          url: "{{ route('selleredition') }}",
          type: "post",
          dataType: "json",
          data: parameters,
          success: function (data) {

            if (data.code === 100) {
              editSellerModal.hide();
              setTimeout(function(){
                //datatableInstance.ajax.reload();
                window.parent.location.reload();
              }, 2000);
              toastr.success("Marchand modifiée avec succès");
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
      $("body").on("click", ".delete-seller", async function (event) {
        event.preventDefault();
        let name = $(this).parent().parent().children().first().html();
        let id = $(this).attr("about");

        let $this = $(this);
        if (confirm(`Voulez-vous réellement supprimer la boutique ${name} ?`)) {
          //Loader.setLoading($this);
          $.ajax({
            url: "{{ route('sellerdeletion') }}",
            data: {
              id: id,
              _token: "{{ csrf_token() }}"
            },
            type: "post",
            dataType: "json",
            success: function (data) {
              if (data.code === 100) {
                toastr.success("Marchand supprimée avec succès!");
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

      datatableInstance = $("#seller-table").DataTable({
        "processing": true,
        "language": {
          "search": '',
          "searchPlaceholder": "Rechercher..."
        },
      });
    });
</script>
@endsection

