@extends('admin.components.layout')

@section('main')

<h4 class="py-3 mb-0">
  <span class="text-muted fw-light">eCommerce /</span><span class="fw-medium"> Ajouter produit</span>
</h4>

<div class="app-ecommerce">
  <div class="row">

    <!-- First column-->
    <div class="col-12 col-lg-12">
      <!-- Product Information -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="card-tile mb-0">Informations sur le produit</h5>
        </div>

        <!-- Modal -->
        <form action="">
<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Importer fichier excel</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
      <div class="col-lg-6">

   
      <label class="form-label" for="	product_image2">Fichier excel</label>
      <input type="file" class="form-control" id="product_image2" name="excel" />
   
                  
                    </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </div>
  </div>
</div>
</form>
        <div class="card-body">
            <form action="{{ route('storeproduct') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="designation_tech">Désignation produit<span
                            class="text-danger">*</span></label></label>
                        <input type="text"  required class="form-control" id="designation_tech" placeholder="Désignation article" name="name" aria-label="Désignation article">
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="qty">Quantité <span
                            class="text-danger">*</span></label></label>
                        <input type="number"  required class="form-control" id="quantity" placeholder="Quantité" name="quantity" aria-label="Quantité">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="price">Prix unitaire <span
                            class="text-danger">*</span></label></label>
                        <input type="number"  required class="form-control" id="unit_price" placeholder="Prix" name="unit_price" aria-label="Prix">
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="locality">Localité</label>
                        <input type="text"  required class="form-control" id="locality" placeholder="Désignation article" name="locality" aria-label="Localité">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="bar_code">Code bar</label>
                        <input type="text"  required class="form-control" id="bar_code" placeholder="Code bar" name="bar_code" aria-label="Code bar">
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="form-repeater-1-1">Catégorie <span
                            class="text-danger">*</span></label></label>
                        <select class="form-select" id="category" name="category_id" aria-label="Catégorie"  required>
                            <option selected>Sélectionnez une catégorie</option>
                            @foreach ($categories as $category)
                                <option style="color : black" value="{{ $category->{'id'} }}">{{ $category->{'name'} }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="form-repeater-1-1">Boutique <span
                            class="text-danger">*</span></label></label>
                        <select class="form-select" id="shop_id" name="shop_id" aria-label="Catégorie" required>
                            <option selected>Sélectionnez une boutique</option>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->{'id'} }}">{{ $shop->{'shop_name'} }}</option>
                            @endforeach
                        </select>
                    </div>
                    <!-- <div class="col-lg-6">
                        <label class="form-label" for="shop_logo">Logo boutique</label>
                        <input type="file" class="form-control" id="shop_logo" name="shop_logo" />
                    </div> -->
                </div>
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="bar_code">Commission</label>
                        <input  required type="text" class="form-control" id="commission" placeholder="Marque" name="commission" aria-label="Marque">
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="product_image1">Image 1 produit <span
                            class="text-danger">*</span></label></label>
                        <input  required type="file" class="form-control" id="product_image1" name="product_image1" />
                    </div>
                </div>
                <!-- <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="	product_image2">Image 2 produit</label>
                        <input type="file" class="form-control" id="product_image2" name="product_image2" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="product_image3">Image 3 produit </label>
                        <input type="file" class="form-control" id="product_image3" name="product_image3" />
                    </div>
                </div> -->
                <!-- <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="product_video1">Vidéo 1 produit </label>
                        <input type="file" class="form-control" id="product_video1" name="product_video1" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="product_video2">Vidéo 2 produit</label>
                        <input type="file" class="form-control" id="product_video2" name="product_video2" />
                    </div>
                </div> -->
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="product_length">Longueur produit</label>
                        <input  required type="text" class="form-control" id="product_length" name="product_length" placeholder="Longueur article" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="product_width">Largeur produit </label>
                        <input  required type="text" class="form-control" id="product_width" name="product_width" placeholder="Largeur article" />
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="product_epaisseur">Epaisseur produit</label>
                        <input  required type="text" class="form-control" id="product_epaisseur" name="product_epaisseur" placeholder="Epaisseur article" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="product_volume">Volume produit </label>
                        <input  required type="text" class="form-control" id="product_volume" name="product_volume" placeholder="Volume article" />
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="product_color">Couleur produit</label>
                        <input   required type="text" class="form-control" id="product_color" name="product_color" placeholder="Couleur article" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="product_weigth">Poids produit </label>
                        <input  required type="text" class="form-control" id="product_weigth" name="product_weigth" placeholder="Poids article" />
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="parameter1">Paramètre 1 produit</label>
                        <input  required type="text" class="form-control" id="parameter1" name="parameter1" placeholder="Paramètre 1 article" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="parameter2">Paramètre 2 produit </label>
                        <input  required type="text" class="form-control" id="parameter2" name="parameter2" placeholder="Paramètre 2 article" />
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col lg-6">
                        <label class="form-label" for="description">Description article <span
                            class="text-danger">*</span></label></label>
                        <textarea  required class="form-control" name="description" id="description" cols="10" rows="2"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="">
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#exampleModal">
                 Importer fichier excel
                    </button>


                      <button type="submit" class="btn btn-primary">Ajouter article</button>
                    </div>
                  </div>
            </form>

        </div>

    </div>
</div>
<!-- / Content -->
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
@endsection

