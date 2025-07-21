@extends('admin.components.layout')

@section('main')
@php
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Shop;

$categories = Category::latest()->get();
$shops = Shop::latest()->get();
@endphp
<!-- Edit Product modal -->


<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">eCommerce /</span> Liste des utilisateurs
</h4>

@if (session()->has('message'))
        <div class="alert alert-success">
            {{ session()->get('message') }}
        </div>
    @endif
<div class="card">
  <div class="card-header">
    <h5 class="card-title mb-0">Filter</h5>
    <div class="d-flex justify-content-between align-items-center row py-3 gap-3 gap-md-0">
      <div class="col-md-4 product_status"></div>
      <div class="col-md-4 product_category"></div>
      <div class="col-md-4 product_stock"></div>
    </div>
  </div>
  <div class="card-datatable table-responsive text-nowrap">
    <table id="product-table" class="datatables-products table">
      <thead class="border-top">
        <tr>
        <th>User</th>
          <th class="text-nowrap">User Id</th>
          <th>Country</th>
          <th>Phone</th>
          <th>Email</th>
          <th>City</th>
          <th>Role</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      @foreach ($listUsers as $item)
        <tr>
               
             
               <td>{{ $item->name }}</td>
               <td>{{ $item->id }}</td>
               <td>{{ $item->country }}</td>
               <td>{{ $item->phone }}</td>
               <td>{{ $item->email }}</td>
               <td>{{ $item->city }}</td>
               <td>{{ $item->role }}</td>
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
               @if($item->status == "pending")
               <a href="{{route('status.change',['id'=>$item->id, 'base'=>'users' , 'status'=>'Success' ])}}"    class='badge bg-success' >Activer</a>
               @endif
               @if($item->status == "Success")
               <a href="{{route('status.change',['id'=>$item->id, 'base'=>'users' , 'status'=>'Pending' ])}}"    class='badge bg-danger' >Desactiver</a>
               @endif

               @if($item->role == "user")
               <a href="{{route('role.change',['id'=>$item->id , 'role'=>'admin' ])}}"    class='badge bg-info' >nommer admin</a>
               @endif
               @if($item->role == "admin")
               <a href="{{route('role.change',['id'=>$item->id,  'role'=>'user'])}}"    class='badge bg-warning' >nommer user</a>
               @endif
               @if($item->role != "agent")
               <a href="{{route('role.change',['id'=>$item->id, 'role'=>'agent' ])}}"    class='badge bg-dark' >nommer agent</a>
               @endif
               @if($item->role == "agent")
               <a href="{{route('role.change',['id'=>$item->id, 'role'=>'user' ])}}"    class='badge bg-warning' >Desactiver agent</a>
               @endif

<!-- 
               @if($item->status == "failed")
               <a href=""    class='badge bg-warning' >restaurer</a>

               @endif -->

              
              
              
              
              
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

    let productEditionId;
    $(document).ready(function (){

        //Click on .edit-cat
      $("body").on("click", ".edit-product", function (event) {
        event.preventDefault();

        let id = $(this).attr("about");
        let designation = $(this).parent().parent().children(":eq(1)").html();
        let quantity = $(this).parent().parent().children(":eq(2)").html();
        let price = $(this).parent().parent().children(":eq(3)").html();
        let locality = $(this).parent().parent().children(":eq(4)").html();
        let category = $(this).parent().parent().children(":eq(5)").html();
        let shop = $(this).parent().parent().children(":eq(6)").html();
        let commission = $(this).parent().parent().children(":eq(7)").html();
        let color = $(this).parent().parent().children(":eq(8)").html();
        let description = $(this).parent().parent().children(":eq(8)").html();

        $("#designation_tech").val(designation);
        $("#quantity").val(quantity);
        $("#unit_price").val(price);
        $("#locality").val(locality);
        $("#category").val(category);
        $("#shop").val(shop);
        $("#shop").trigger('change');
        $("#commission").val(commission);
        $("#product_color").val(color);
        $("#description").val(description);

        editProductModal = new bootstrap.Modal(document.getElementById("editProductModal"), {
          "backdrop": "static",
          "keyboard": false,
        });
        editProductModal.show();
        productEditionId = id;
      });

      //Click on editProductSaveButton
      $("#editProductSaveButton").on("click", function (event) {
        event.preventDefault();
        let $this = $(this);
        let parameters = {
          id: productEditionId
        };
        let designation = $("#designation_tech").val();
        if (designation === "") {
          toastr.warning("Veuillez renseigner le champ désignation du produit");
          return;
        }
        parameters["designation"] = designation;

        let quantity = $("#quantity").val();
        parameters["quantity"] = quantity;

        let price = $("#unit_price").val();
        parameters["price"] = price;

        let locality = $("#locality").val();
        parameters["locality"] = locality;

        let category = $("#category").val();
        parameters["category"] = category;

        let shop = $("#shop").val();
        parameters["shop"] = shop;

        let commission = $("#commission").val();
        parameters["commission"] = commission;

        let color = $("#product_color").val();
        parameters["product_color"] = color;

        parameters["_token"] = "{{ csrf_token() }}",
        //Loader.setLoading($this);
        $.ajax({
          url: "{{ route('productedition') }}",
          type: "post",
          dataType: "json",
          data: parameters,
          success: function (data) {

            if (data.code === 100) {
              editProductModal.hide();
              setTimeout(function(){
                //datatableInstance.ajax.reload();
                window.parent.location.reload();
              }, 2000);
              toastr.success("Produit modifié avec succès");
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
      $("body").on("click", ".delete-product", async function (event) {
        event.preventDefault();
        let productDesignation = $(this).parent().parent().children(":eq(1)").html();
        let id = $(this).attr("about");

        let $this = $(this);
        if (confirm(`Voulez-vous réellement supprimer le produit ${productDesignation} ?`)) {
          //Loader.setLoading($this);
          $.ajax({
            url: "{{ route('productdeletion') }}",
            data: {
              id: id,
              _token: "{{ csrf_token() }}"
            },
            type: "post",
            dataType: "json",
            success: function (data) {
              if (data.code === 100) {
                toastr.success("Produit supprimé avec succès!");
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

      datatableInstance = $("#product-table").DataTable({
        "processing": true,
        "language": {
          "search": '',
          "searchPlaceholder": "Rechercher..."
        },
      });
    });
</script>
@endsection

