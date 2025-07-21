@extends('merchand.components.layout')

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
<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier produit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editproductForm">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label" for="designation_tech">Désignation produit<span
                                class="text-danger">*</span></label></label>
                            <input type="text" class="form-control" id="designation_tech" placeholder="Désignation article" name="designation_tech" aria-label="Désignation article">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="qty">Quantité <span
                                class="text-danger">*</span></label></label>
                            <input type="number" class="form-control" id="quantity" placeholder="Quantité" name="quantity" aria-label="Quantité">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="price">Prix unitaire <span
                                class="text-danger">*</span></label></label>
                            <input type="number" class="form-control" id="unit_price" placeholder="Prix" name="unit_price" aria-label="Prix">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label" for="form-repeater-1-1">Catégorie <span
                                class="text-danger">*</span></label></label>
                            <select class="form-select" id="category" name="category_id" aria-label="Catégorie">
                                <option selected>Sélectionnez une catégorie</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->{'id'} }}">{{ $category->{'category_name'} }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="form-repeater-1-1">Boutique <span
                                class="text-danger">*</span></label></label>
                            <select class="form-select" id="shop_id" name="shop_id" aria-label="Catégorie">
                                <option selected>Sélectionnez une boutique</option>
                                @foreach ($shops as $shop)
                                    <option value="{{ $shop->{'id'} }}">{{ $shop->{'shop_name'} }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="shop_logo">Logo boutique</label>
                            <input type="file" class="form-control" id="shop_logo" name="shop_logo" />
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label" for="bar_code">Code bar</label>
                            <input type="text" class="form-control" id="bar_code" placeholder="Code bar" name="bar_code" aria-label="Code bar">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="bar_code">Commission</label>
                            <input type="text" class="form-control" id="commission" placeholder="Marque" name="commission" aria-label="Marque">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="product_color">Couleur produit</label>
                            <input type="text" class="form-control" id="product_color" name="product_color" placeholder="Couleur article" />
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label" for="product_image1">Image 1 produit <span
                                class="text-danger">*</span></label></label>
                            <input type="file" class="form-control" id="product_image1" name="product_image1" />
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="	product_image2">Image 2 produit</label>
                            <input type="file" class="form-control" id="product_image2" name="product_image2" />
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="product_image3">Image 3 produit </label>
                            <input type="file" class="form-control" id="product_image3" name="product_image3" />
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label" for="product_video1">Vidéo 1 produit </label>
                            <input type="file" class="form-control" id="product_video1" name="product_video1" />
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="product_video2">Vidéo 2 produit</label>
                            <input type="file" class="form-control" id="product_video2" name="product_video2" />
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="product_length">Longueur produit</label>
                            <input type="text" class="form-control" id="product_length" name="product_length" placeholder="Longueur article" />
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label" for="product_width">Largeur produit </label>
                            <input type="text" class="form-control" id="product_width" name="product_width" placeholder="Largeur article" />
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="product_epaisseur">Epaisseur produit</label>
                            <input type="text" class="form-control" id="product_epaisseur" name="product_epaisseur" placeholder="Epaisseur article" />
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="product_volume">Volume produit </label>
                            <input type="text" class="form-control" id="product_volume" name="product_volume" placeholder="Volume article" />
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label" for="product_weigth">Poids produit </label>
                            <input type="text" class="form-control" id="product_weigth" name="product_weigth" placeholder="Poids article" />
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="parameter1">Paramètre 1 produit</label>
                            <input type="text" class="form-control" id="parameter1" name="parameter1" placeholder="Paramètre 1 article" />
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label" for="parameter2">Paramètre 2 produit </label>
                            <input type="text" class="form-control" id="parameter2" name="parameter2" placeholder="Paramètre 2 article" />
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-lg-4">
                            <label class="form-label" for="locality">Localité</label>
                            <input type="text" class="form-control" id="locality" placeholder="Désignation article" name="locality" aria-label="Localité">
                        </div>

                    </div>
                    <div class="row mb-3">
                        <div class="col lg-6">
                            <label class="form-label" for="description">Description article <span
                                class="text-danger">*</span></label></label>
                            <textarea class="form-control" name="description" id="description" cols="10" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary"
                    data-bs-dismiss="modal">Fermer</button>
                <button id="editProductSaveButton" type="button"
                    class="btn btn-primary">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light"></span> Liste des produits
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
          <th>Référence</th>
          <th>Désignation produit</th>
          <th>Stock init</th>
          <th>Prix unitaire (FCFA)</th>
          <th>Commission (FCFA)</th>
          <!-- <th>Image 1 produit</th>
          <th>Image 2 produit</th>
          <th>Image 3 produit</th>
          <th>Vidéo 1 produit</th> -->
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($listProducts as $item)

        <tr>
               <td>{{ $item->{'ref'} }}</td>
               <td>{{ $item->{'name'} }}</td>
               <td>{{ $item->{'stock_init'} }}</td>
               <td>{{ number_format($item->{'price'}, 2, ',', ' ') }}</td>
               <td>{{ $item->{'commission'} }} </td>
              
               <!-- <td>
                <img style="height: 50px;" src="{{ asset($item->{'product_image1'}) }}" alt="">
                <br />
                <a href="{{route('editproductimg', $item->{'id'})}}" class="btn btn-primary">Modifier</a> 
               </td>
               <td>
                <img style="height: 50px;" src="{{ asset($item->{'product_image2'}) }}" alt="">
                <br />
                <a href="{{route('editproductimg', $item->{'id'})}}" class="btn btn-primary">Modifier</a> 
               </td>
               <td>
                <img style="height: 50px;" src="{{ asset($item->{'product_image3'}) }}" alt="">
                <br />
                <a href="{{route('editproductimg', $item->{'id'})}}" class="btn btn-primary">Modifier</a>  
               </td>
               <td>
                <img style="height: 50px;" src="{{ asset($item->{'product_video1'}) }}" alt="">
                <br />
                <a href="{{route('editproductimg', $item->{'id'})}}" class="btn btn-primary">Modifier</a>  
               </td> -->
              
           

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

               <a class="edit-product" about="{{ $item->{'id'} }}" href="javascript:void(0);" title="Modifier boutique"><i class="ti ti-pencil me-1 text-primary"></i></a>
               
               @if($item->status == "pending")
               <a href="{{route('status.change',['id'=>$item->id, 'base'=>'products' , 'status'=>'Success' ])}}"    class='badge bg-success' >Activer</a>
               @endif
               @if($item->status == "Success")
               <a href="{{route('status.change',['id'=>$item->id, 'base'=>'products' , 'status'=>'Pending' ])}}"    class='badge bg-danger' >Desactiver</a>
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

