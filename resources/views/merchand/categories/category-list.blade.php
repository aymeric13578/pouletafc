@extends('merchand.components.layout')

@section('main')
<!-- Edit Category modal -->
<div class="modal fade" id="editCatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier Catégorie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editCatForm">
                    @csrf
                  <div class="row">
                    <div class="col mb-3">
                        <label class="form-label" for="category_name">Nom catégorie</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" placeholder="Saisissez le nom de la catégorie" />
                    </div>
                  </div>
                  <div class="row">
                    <div class="col mb-3">
                        <label class="form-label" for="category_code">Code catégorie</label>
                        <input type="text" class="form-control" id="category_code" name="category_code" placeholder="Saisissez le code de la catégorie" />
                    </div>
                  </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary"
                    data-bs-dismiss="modal">Fermer</button>
                <button id="editCatSaveButton" type="button"
                    class="btn btn-primary">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">eCommerce /</span> Liste des catégories
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
  <div class="card-datatable table-responsive">
    <table id="category-table" class="datatables table">
      <thead class="border-top">
        <tr>
          <th>Nom catégorie</th>
          <th>Code catégorie</th>
          <th>Statut</th>

          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($categories as $item)
        <tr>

               <td>{{ $item->{'name'} }}</td>
               <td>{{ $item->{'ref'} }}</td>

               
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

               <a class="edit-cat" about="{{ $item->{'id'} }}" href="javascript:void(0);" title="Modifier catégorie"><i class="ti ti-pencil me-1 text-primary"></i></a>


               
               @if($item->status == "pending" || $item->status == null)
               <a href="{{route('status.change',['id'=>$item->id, 'base'=>'categories' , 'status'=>'Success' ])}}"    class='badge bg-success' >Activer</a>
               @endif
               @if($item->status == "Success")
               <a href="{{route('status.change',['id'=>$item->id, 'base'=>'categories' , 'status'=>'Pending' ])}}"    class='badge bg-danger' >Desactiver</a>
               @endif


              
              
              </td>
             
             </tr>
          @endforeach
    </tbody>
    </table>
  </div>
</div>


          </div>
          <!-- / Content -->

@endsection
@section('page-script')
<script type="text/javascript">

    let catEditionId;
    $(document).ready(function (){

        //Click on .edit-cat
      $("body").on("click", ".edit-cat", function (event) {
        event.preventDefault();
        let id = $(this).attr("about");
        let designation = $(this).parent().parent().children().first().html();
        let code = $(this).parent().parent().children(":eq(1)").html();
        $("#category_name").val(designation);
        $("#category_code").val(code);
        editCatModal = new bootstrap.Modal(document.getElementById("editCatModal"), {
          "backdrop": "static",
          "keyboard": false,
        });
        editCatModal.show();
        catEditionId = id;
      });

      //Click on editCatSaveButton
      $("#editCatSaveButton").on("click", function (event) {
        event.preventDefault();
        let $this = $(this);
        let parameters = {
          id: catEditionId
        };
        let designation = $("#category_name").val();
        if (designation === "") {
          toastr.warning("Veuillez renseigner le champ nom de la catégorie");
          return;
        }
        parameters["designation"] = designation;
        let code = $("#category_code").val();
        if (code === "") {
          toastr.warning("Veuillez renseigner le champ code de la catégorie");
          return;
        }
        parameters["code"] = code;

        parameters["_token"] = "{{ csrf_token() }}",
        //Loader.setLoading($this);
        $.ajax({
          url: "{{ route('catedition') }}",
          type: "post",
          dataType: "json",
          data: parameters,
          success: function (data) {

            if (data.code === 100) {
              editCatModal.hide();
              setTimeout(function(){
                //datatableInstance.ajax.reload();
                window.parent.location.reload();
              }, 2000);
              toastr.success("Catégorie modifiée avec succès");
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
      $("body").on("click", ".delete-cat", async function (event) {
        event.preventDefault();
        let catDesignation = $(this).parent().parent().children().first().html();
        let id = $(this).attr("about");
        {{--  let shouldDelete = await askConfirmation({
          title: "Suppression",
          message: `Voulez-vous réellement supprimer la catégorie ${catDesignation} ?`,
          cancelLabel: "Annuler",
          confirmLabel: "Confirmer",
        });  --}}

        let $this = $(this);
        if (confirm(`Voulez-vous réelement supprimer la catégorie ${catDesignation} ?`)) {
          //Loader.setLoading($this);
          $.ajax({
            url: "{{ route('catdeletion') }}",
            data: {
              id: id,
              _token: "{{ csrf_token() }}"
            },
            type: "post",
            dataType: "json",
            success: function (data) {
              if (data.code === 100) {
                toastr.success("Catégorie supprimée avec succès!");
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

      datatableInstance = $("#category-table").DataTable({
        "processing": true,
        "language": {
          "search": '',
          "searchPlaceholder": "Rechercher..."
        },
      });
    });
</script>
@endsection

