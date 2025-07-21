@extends('admin.components.layout')

@section('main')


  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Ajouter une nouvelle sous-catégorie</h5>
      <div class="d-flex justify-content-between align-items-center row py-3 gap-3 gap-md-0">
        <div class="col-md-4 product_status"></div>
        <div class="col-md-4 product_category"></div>
        <div class="col-md-4 product_stock"></div>
      </div>
    </div>
    <div class="col-xxl">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error )
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>

            @endif
            <form action="{{route('storesubcategory')}}" method="POST"  enctype="multipart/form-data">
                @csrf
              <div class="row mb-3">
                <label class="col-sm-2 col-form-label" for="basic-default-name">Nom sous-catégorie</label>
                <div class="col-sm-10">
                  <input type="text" class="form-control" id="subcategory_name" name="subcategory_name" placeholder="Saisissez le nom de la catégorie" />
                </div>
              </div>
              <div class="row mb-3">
                <label class="col-sm-2 col-form-label" for="subcategory_code">Code sous-catégorie</label>
                <div class="col-sm-10">
                  <input type="text" class="form-control" id="subcategory_code" name="subcategory_code" placeholder="Saisissez le nom de la catégorie" />
                </div>
              </div>

              <div class="row mb-3">
                <label class="col-sm-2 col-form-label" for="subcategory_code">Image</label>
                <div class="col-sm-10">
                  <input type="file" class="form-control" id="subcategory_code" name="image" placeholder="Saisissez le nom de la catégorie" />
                </div>
              </div>

              <div class="row mb-3">
                <label for="category_name" class="col-sm-2 col-form-label">Nom catégorie</label>
                <div class="col-sm-10">
                    <select class="form-select" id="category_id" name="category_id" aria-label="Default select example">
                        <option selected>Sélectionnez une catégorie</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->{'id'} }}">{{ $category->{'name'} }}</option>
                        @endforeach
                    </select>
                </div>
              </div>



              <div class="row mb-3">
                <label class="col-sm-2 col-form-label" for="subcategory_code">Description</label>
                <div class="col-sm-10">
                 <textarea  required class="form-control" name="description" id="description" cols="10" rows="2"></textarea>

                </div>
              </div>


              <div class="modal-footer">
                <div class="">
                  <button type="submit" class="btn btn-primary">Ajouter sous-catégorie</button>
                </div>
              </div>
            </form>
          </div>
    </div>
  </div>
@endsection
