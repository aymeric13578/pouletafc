@extends('admin.components.layout')

@section('main')
@php
use App\Models\Category;

$categories = Category::latest()->get();
@endphp
<!-- Edit Company modal -->
<div class="modal fade" id="editSubCatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier sous-catégorie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editSubCatForm">
                    @csrf
                  <div class="row">
                    <div class="col mb-3">
                        <label class="form-label" for="subcategory_name">Nom sous-catégorie</label>
                        <input type="text" class="form-control" id="subcategory_name" name="subcategory_name" placeholder="Saisissez le nom de la catégorie" />
                    </div>
                  </div>
                  <div class="row">
                    <div class="col mb-3">
                        <label class="form-label" for="subcategory_code">Code catégorie</label>
                        <input type="text" class="form-control" id="subcategory_code" name="subcategory_code" placeholder="Saisissez le code de la sous-catégorie" />
                    </div>
                  </div>
                  <div class="row">
                    <div class="col mb-3">
                        <label for="category_name" class="form-label">Nom catégorie</label>
                        <select class="form-select" id="category_id" name="category_id" aria-label="Default select example">
                            <option selected>Sélectionnez une catégorie</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->{'id'} }}">{{ $category->{'category_name'} }}</option>
                            @endforeach
                        </select>
                    </div>
                  </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary"
                    data-bs-dismiss="modal">Fermer</button>
                <button id="editSubCatSaveButton" type="button"
                    class="btn btn-primary">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">eCommerce /</span> Liste des sous-catégorie
</h4>

<!-- Product List Widget -->
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
    <table id="subcategory-table" class="datatables table">
      <thead class="border-top">
        <tr>
          <th>Nom sous-catégorie</th>
          <th>Code catégorie</th>
          <th>Statut</th> 
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($subCategories as $item)
        <tr>
            <td>{{ $item->{'name'} }}</td>
            <td>{{ $item->{'ref'} }}</td>
           







                 <td>
                               <!-- Button trigger modal -->
<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#{{ $item->{'ref'} }}">
 voir
</button>

<!-- Modal -->
<div class="modal fade" id="{{ $item->{'ref'} }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">{{ $item->{'name'} }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
  <img style="" src="{{ asset($item->{'image'}) }}" alt="">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>

      </div>
    </div>
  </div>
</div>
           
               
               
 </td>












          
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

               <a class="edit-subcat" about="{{ $item->{'id'} }}" href="javascript:void(0);" title="Modifier catégorie"><i class="ti ti-pencil me-1 text-primary"></i></a>

               @if($item->status == "pending" || $item->status == null)
               <a href="{{route('status.change',['id'=>$item->id, 'base'=>'shops' , 'status'=>'Success' ])}}"    class='badge bg-success' >Activer</a>
               @endif
               @if($item->status == "Success")
               <a href="{{route('status.change',['id'=>$item->id, 'base'=>'shops' , 'status'=>'Pending' ])}}"    class='badge bg-danger' >Desactiver</a>
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

    let SubCatEditionId;
    $(document).ready(function (){

        //Click on .edit-subcat
      $("body").on("click", ".edit-subcat", function (event) {
        event.preventDefault();
        let id = $(this).attr("about");
        let designation = $(this).parent().parent().children().first().html();
        let code = $(this).parent().parent().children(":eq(1)").html();
        let catId = $(this).parent().parent().children(":eq(4)").html();

        $("#subcategory_name").val(designation);
        $("#subcategory_code").val(code);
        $("#category_id").val(catId);

        editSubCatModal = new bootstrap.Modal(document.getElementById("editSubCatModal"), {
          "backdrop": "static",
          "keyboard": false,
        });
        editSubCatModal.show();
        SubCatEditionId = id;
      });

      //Click on editCatSaveButton
      $("#editSubCatSaveButton").on("click", function (event) {
        event.preventDefault();
        let $this = $(this);
        let parameters = {
          id: SubCatEditionId
        };
        let designation = $("#subcategory_name").val();
        if (designation === "") {
          toastr.warning("Veuillez renseigner le champ nom de la sous-catégorie");
          return;
        }
        parameters["subcategory_name"] = designation;
        let code = $("#subcategory_code").val();
        if (code === "") {
          toastr.warning("Veuillez renseigner le champ code de la catégorie");
          return;
        }
        parameters["subcategory_code"] = code;

        let category_id = $("#category_id").val();
        parameters["category_id"] = category_id;

        parameters["_token"] = "{{ csrf_token() }}",
        //Loader.setLoading($this);
        $.ajax({
          url: "{{ route('subcatedition') }}",
          type: "post",
          dataType: "json",
          data: parameters,
          success: function (data) {

            if (data.code === 100) {
              editSubCatModal.hide();
              setTimeout(function(){
                //datatableInstance.ajax.reload();
                window.parent.location.reload();
              }, 2000);
              toastr.success("Sous-catégorie modifiée avec succès");
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

      //Click on .delete-subcat
      $("body").on("click", ".delete-subcat", async function (event) {
        event.preventDefault();
        let subcatDesignation = $(this).parent().parent().children().first().html();
        let id = $(this).attr("about");

        let $this = $(this);
        if (confirm(`Voulez-vous réelement supprimer la sous-catégorie ${subcatDesignation} ?`)) {
          //Loader.setLoading($this);
          $.ajax({
            url: "{{ route('subcatdeletion') }}",
            data: {
              id: id,
              _token: "{{ csrf_token() }}"
            },
            type: "post",
            dataType: "json",
            success: function (data) {
              if (data.code === 100) {
                toastr.success("Sous-catégorie supprimée avec succès!");
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

      datatableInstance = $("#subcategory-table").DataTable({
        "processing": true,
        "language": {
          "search": '',
          "searchPlaceholder": "Rechercher..."
        },
      });
    });
</script>
@endsection

