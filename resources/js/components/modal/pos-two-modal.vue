<template>
  <!-- Payment Completed -->
  <div
    class="modal fade modal-default"
    id="payment-completed"
    aria-labelledby="payment-completed"
  >
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <div class="success-wrap text-center">
            <form @submit.prevent="submitForm">
              <div class="icon-success bg-success text-white mb-2">
                <i class="ti ti-check"></i>
              </div>
              <h3 class="mb-2">Payment Completed</h3>
              <p class="mb-3">Do you want to Print Receipt for the Completed Order</p>
              <div
                class="d-flex align-items-center justify-content-center gap-2 flex-wrap"
              >
                <button
                  type="button"
                  class="btn btn-md btn-secondary"
                  data-bs-toggle="modal"
                  data-bs-target="#print-receipt"
                >
                  Print Receipt<i class="feather-arrow-right-circle icon-me-5 ms-2"></i>
                </button>
                <button type="submit" class="btn btn-md btn-primary">Next Order</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- /Payment Completed -->

  <!-- Print Receipt -->
  <div
    class="modal fade modal-default"
    id="print-receipt"
    aria-labelledby="print-receipt"
  >
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body">
          <div class="icon-head text-center invoice-logo d-flex justify-content-center">
            <a href="javascript:void(0);">
              <img src="@/assets/images/logo.svg" width="130" class="img-fluid dark-logo" alt="logo">
              <img src="@/assets/images/logo-white.svg" width="130" class="img-fluid white-logo" alt="logo">
            </a>
          </div>
          <div class="text-center info text-center">
            <h6>Dreamguys Technologies Pvt Ltd.,</h6>
            <p class="mb-0">Phone Number: +1 5656665656</p>
            <p class="mb-0">
              Email: <a href="mailto:example@gmail.com">example@gmail.com</a>
            </p>
          </div>
          <div class="tax-invoice">
            <h6 class="text-center">Tax Invoice</h6>
            <div class="row">
              <div class="col-sm-12 col-md-6">
                <div class="invoice-user-name"><span>Name: </span>John Doe</div>
                <div class="invoice-user-name"><span>Invoice No: </span>CS132453</div>
              </div>
              <div class="col-sm-12 col-md-6">
                <div class="invoice-user-name"><span>Customer Id: </span>#LL93784</div>
                <div class="invoice-user-name"><span>Date: </span>01.07.2022</div>
              </div>
            </div>
          </div>
          <table class="table-borderless w-100 table-fit">
            <thead>
              <tr>
                <th># Item</th>
                <th>Price</th>
                <th>Qty</th>
                <th class="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>1. Red Nike Laser</td>
                <td>$50</td>
                <td>3</td>
                <td class="text-end">$150</td>
              </tr>
              <tr>
                <td>2. Iphone 14</td>
                <td>$50</td>
                <td>2</td>
                <td class="text-end">$100</td>
              </tr>
              <tr>
                <td>3. Apple Series 8</td>
                <td>$50</td>
                <td>3</td>
                <td class="text-end">$150</td>
              </tr>
              <tr>
                <td colspan="4">
                  <table class="table-borderless w-100 table-fit">
                    <tbody>
                      <tr>
                        <td class="fw-bold">Sub Total :</td>
                        <td class="text-end">$700.00</td>
                      </tr>
                      <tr>
                        <td class="fw-bold">Discount :</td>
                        <td class="text-end">-$50.00</td>
                      </tr>
                      <tr>
                        <td class="fw-bold">Shipping :</td>
                        <td class="text-end">0.00</td>
                      </tr>
                      <tr>
                        <td class="fw-bold">Tax (5%) :</td>
                        <td class="text-end">$5.00</td>
                      </tr>
                      <tr>
                        <td class="fw-bold">Total Bill :</td>
                        <td class="text-end">$655.00</td>
                      </tr>
                      <tr>
                        <td class="fw-bold">Due :</td>
                        <td class="text-end">$0.00</td>
                      </tr>
                      <tr>
                        <td class="fw-bold">Total Payable :</td>
                        <td class="text-end">$655.00</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </tbody>
          </table>
          <div class="text-center invoice-bar">
            <div class="border-bottom border-dashed">
              <p>
                **VAT against this challan is payable through central registration. Thank
                you for your business!
              </p>
            </div>
            <a href="javascript:void(0);">
              <img :src="'/img/barcode/barcode-03.jpg'" alt="Barcode" />
            </a>
            <p class="text-dark fw-bold">Sale 31</p>
            <p>Thank You For Shopping With Us. Please Come Again</p>
            <a href="javascript:void(0);" class="btn btn-md btn-primary">Print Receipt</a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- /Print Receipt -->

  <!-- Products -->
  <div
    class="modal fade modal-default pos-modal"
    id="products"
    aria-labelledby="products"
  >
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center">
            <h5 class="me-4">Products</h5>
          </div>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="card bg-light mb-3">
            <div class="card-body">
              <div
                class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3"
              >
                <span class="badge bg-dark fs-12">Order ID : #45698</span>
                <p class="fs-16">Number of Products : 02</p>
              </div>
              <div class="product-wrap h-auto">
                <div
                  class="product-list bg-white align-items-center justify-content-between"
                >
                  <div
                    class="d-flex align-items-center product-info"
                    data-bs-toggle="modal"
                    data-bs-target="#products"
                  >
                    <a href="javascript:void(0);" class="pro-img">
                      <img
                        :src="'/img/products/pos-product-16.png'"
                        alt="Products"
                      />
                    </a>
                    <div class="info">
                      <h6><a href="javascript:void(0);">Red Nike Laser</a></h6>
                      <p>Quantity : 04</p>
                    </div>
                  </div>
                  <p class="text-teal fw-bold">$2000</p>
                </div>
                <div
                  class="product-list bg-white align-items-center justify-content-between"
                >
                  <div
                    class="d-flex align-items-center product-info"
                    data-bs-toggle="modal"
                    data-bs-target="#products"
                  >
                    <a href="javascript:void(0);" class="pro-img">
                      <img
                        :src="'/img/products/pos-product-17.png'"
                        alt="Products"
                      />
                    </a>
                    <div class="info">
                      <h6><a href="javascript:void(0);">Iphone 11S</a></h6>
                      <p>Quantity : 04</p>
                    </div>
                  </div>
                  <p class="text-teal fw-bold">$3000</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- /Products -->

  <div
    class="modal fade"
    id="create"
    tabindex="-1"
    aria-labelledby="create"
    aria-hidden="true"
  >
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <form @submit.prevent="submitForm">
          <div class="modal-body pb-1">
            <div class="row">
              <div class="col-lg-6 col-sm-12 col-12">
                <div class="mb-3">
                  <label class="form-label"
                    >Customer Name <span class="text-danger">*</span></label
                  >
                  <input type="text" class="form-control" />
                </div>
              </div>
              <div class="col-lg-6 col-sm-12 col-12">
                <div class="mb-3">
                  <label class="form-label"
                    >Phone <span class="text-danger">*</span></label
                  >
                  <input type="text" class="form-control" />
                </div>
              </div>
              <div class="col-lg-12">
                <div class="mb-3">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" />
                </div>
              </div>
              <div class="col-lg-12">
                <div class="mb-3">
                  <label class="form-label">Address</label>
                  <input type="text" class="form-control" />
                </div>
              </div>
              <div class="col-lg-6 col-sm-12 col-12">
                <div class="mb-3">
                  <label class="form-label">City</label>
                  <input type="text" class="form-control" />
                </div>
              </div>
              <div class="col-lg-6 col-sm-12 col-12">
                <div class="mb-3">
                  <label class="form-label">Country</label>
                  <input type="text" class="form-control" />
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-end gap-2 flex-wrap">
            <button
              type="button"
              class="btn btn-md btn-secondary"
              data-bs-dismiss="modal"
            >
              Cancel
            </button>
            <button type="submit" class="btn btn-md btn-primary">Submit</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Hold -->
  <div
    class="modal fade modal-default pos-modal"
    id="hold-order"
    aria-labelledby="hold-order"
  >
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Hold order</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <form @submit.prevent="submitForm">
          <div class="modal-body">
            <div class="bg-light br-10 p-4 text-center mb-3">
              <h2 class="display-1">4500.00</h2>
            </div>
            <div class="mb-3">
              <label class="form-label"
                >Order Reference <span class="text-danger">*</span></label
              >
              <input class="form-control" type="text" value="" placeholder="" />
            </div>
            <p>
              The current order will be set on hold. You can retreive this order from the
              pending order button. Providing a reference to it might help you to identify
              the order more quickly.
            </p>
          </div>
          <div class="modal-footer d-flex justify-content-end gap-2">
            <button
              type="button"
              class="btn btn-md btn-secondary"
              data-bs-dismiss="modal"
            >
              Cancel
            </button>
            <button type="submit" class="btn btn-md btn-primary">Confirm</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- /Hold -->

  <!-- Edit Product -->
  <div
    class="modal fade modal-default pos-modal"
    id="edit-product"
    aria-labelledby="edit-product"
  >
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Product</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <form @submit.prevent="submitForm">
          <div class="modal-body pb-1">
            <div class="row">
              <div class="col-lg-12">
                <div class="mb-3">
                  <label class="form-label"
                    >Product Name <span class="text-danger">*</span></label
                  >
                  <input
                    type="text"
                    class="form-control"
                    value="Red Nike Laser Show"
                    disabled
                  />
                </div>
              </div>
              <div class="col-lg-6 col-sm-12 col-12">
                <div class="mb-3">
                  <label class="form-label"
                    >Product Price <span class="text-danger">*</span></label
                  >
                  <div class="input-icon-start position-relative">
                    <span class="input-icon-addon text-gray-9">
                      <i class="ti ti-currency-dollar"></i>
                    </span>
                    <input type="text" class="form-control" value="1800" />
                  </div>
                </div>
              </div>
              <div class="col-lg-6 col-sm-12 col-12">
                <div class="mb-3">
                  <label class="form-label"
                    >Tax Type <span class="text-danger">*</span></label
                  >
                  <vue-select
                    :options="Taxtype"
                    v-model="selected"
                    placeholder="Exclusive"
                  />
                </div>
              </div>
              <div class="col-lg-6 col-sm-12 col-12">
                <div class="mb-3">
                  <label class="form-label">Tax <span class="text-danger">*</span></label>
                  <div class="input-icon-start position-relative">
                    <span class="input-icon-addon text-gray-9">
                      <i class="ti ti-percentage"></i>
                    </span>
                    <input type="text" class="form-control" value="15" />
                  </div>
                </div>
              </div>
              <div class="col-lg-6 col-sm-12 col-12">
                <div class="mb-3">
                  <label class="form-label"
                    >Discount Type <span class="text-danger">*</span></label
                  >
                  <vue-select
                    :options="Discount"
                    v-model="selectedTwo"
                    placeholder="Percentage"
                  />
                </div>
              </div>
              <div class="col-lg-6 col-sm-12 col-12">
                <div class="mb-3">
                  <label class="form-label"
                    >Discount <span class="text-danger">*</span></label
                  >
                  <input type="text" class="form-control" value="15" />
                </div>
              </div>
              <div class="col-lg-6 col-sm-12 col-12">
                <div class="mb-3">
                  <label class="form-label"
                    >Sale Unit <span class="text-danger">*</span></label
                  >
                  <vue-select :options="Unit" v-model="selectedThree" placeholder="Kilogram" />
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-end flex-wrap gap-2">
            <button
              type="button"
              class="btn btn-md btn-secondary"
              data-bs-dismiss="modal"
            >
              Cancel
            </button>
            <button type="submit" class="btn btn-md btn-primary">Submit</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- /Edit Product -->

  <!-- Delete Product -->
  <div class="modal fade modal-default" id="delete" aria-labelledby="payment-completed">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <div class="success-wrap text-center">
            <form @submit.prevent="submitForm">
              <div class="icon-success bg-danger-transparent text-danger mb-2">
                <i class="ti ti-trash"></i>
              </div>
              <h3 class="mb-2">Are you Sure!</h3>
              <p class="fs-16 mb-3">
                The current order will be deleted as no payment has been made so far.
              </p>
              <div
                class="d-flex align-items-center justify-content-center gap-2 flex-wrap"
              >
                <button
                  type="button"
                  class="btn btn-md btn-secondary"
                  data-bs-dismiss="modal"
                >
                  No, Cancel
                </button>
                <button type="submit" class="btn btn-md btn-primary">Yes, Delete</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- /Delete Product -->

  <!-- Reset -->
  <div class="modal fade modal-default" id="reset" aria-labelledby="payment-completed">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <div class="success-wrap text-center">
            <form @submit.prevent="submitForm">
              <div class="icon-success bg-purple-transparent text-purple mb-2">
                <i class="ti ti-transition-top"></i>
              </div>
              <h3 class="mb-2">Confirm Your Action</h3>
              <p class="fs-16 mb-3">
                The current order will be cleared. But not deleted if it's persistent.
                Would you like to proceed ?
              </p>
              <div
                class="d-flex align-items-center justify-content-center gap-2 flex-wrap"
              >
                <button
                  type="button"
                  class="btn btn-md btn-secondary"
                  data-bs-dismiss="modal"
                >
                  No, Cancel
                </button>
                <button type="submit" class="btn btn-md btn-primary">Yes, Proceed</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- /Reset -->

  <!-- Recent Transactions -->
  <div class="modal fade pos-modal" id="recents" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Recent Transactions</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="tabs-sets">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link active"
                  id="purchase-tab"
                  data-bs-toggle="tab"
                  data-bs-target="#purchase"
                  type="button"
                  aria-controls="purchase"
                  aria-selected="true"
                  role="tab"
                >
                  Purchase
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link"
                  id="payment-tab"
                  data-bs-toggle="tab"
                  data-bs-target="#payment"
                  type="button"
                  aria-controls="payment"
                  aria-selected="false"
                  role="tab"
                >
                  Payment
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link"
                  id="return-tab"
                  data-bs-toggle="tab"
                  data-bs-target="#return"
                  type="button"
                  aria-controls="return"
                  aria-selected="false"
                  role="tab"
                >
                  Return
                </button>
              </li>
            </ul>
            <div class="tab-content">
              <div
                class="tab-pane fade show active"
                id="purchase"
                role="tabpanel"
                aria-labelledby="purchase-tab"
              >
                <div class="card table-list-card mb-0">
                  <div
                    class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3"
                  >
                    <div class="search-set">
                      <div class="search-input">
                        <a href="javascript:void(0);" class="btn-searchset"
                          ><i class="ti ti-search fs-14 feather-search"></i
                        ></a>
                      </div>
                    </div>
                    <ul class="table-top-head">
                      <li>
                        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Pdf"
                          ><img src="@/assets/images/icons/pdf.svg" alt="img"
                        /></a>
                      </li>
                      <li>
                        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Excel"
                          ><img src="@/assets/images/icons/excel.svg" alt="img"
                        /></a>
                      </li>
                      <li>
                        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Print"
                          ><i class="ti ti-printer"></i
                        ></a>
                      </li>
                    </ul>
                  </div>
                  <div class="card-body">
                    <div class="custom-datatable-filter table-responsive">
                      <table class="table datatable">
                        <thead>
                          <tr>
                            <th class="no-sort">
                              <label class="checkboxs">
                                <input type="checkbox" class="select-all" />
                                <span class="checkmarks"></span>
                              </label>
                            </th>
                            <th>Customer</th>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th class="no-sort">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-27.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Carl Evans</a>
                              </div>
                            </td>
                            <td>INV/SL0101</td>
                            <td>24 Dec 2024</td>
                            <td>$1000</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-02.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Minerva Rameriz</a>
                              </div>
                            </td>
                            <td>INV/SL0102</td>
                            <td>10 Dec 2024</td>
                            <td>$1500</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-05.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Robert Lamon</a>
                              </div>
                            </td>
                            <td>INV/SL0103</td>
                            <td>27 Nov 2024</td>
                            <td>$1500</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-22.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Patricia Lewis</a>
                              </div>
                            </td>
                            <td>INV/SL0104</td>
                            <td>18 Nov 2024</td>
                            <td>$2000</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-03.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Mark Joslyn</a>
                              </div>
                            </td>
                            <td>INV/SL0105</td>
                            <td>06 Nov 2024</td>
                            <td>$800</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-12.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Marsha Betts</a>
                              </div>
                            </td>
                            <td>INV/SL0106</td>
                            <td>25 Oct 2024</td>
                            <td>$750</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-06.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Daniel Jude</a>
                              </div>
                            </td>
                            <td>INV/SL0107</td>
                            <td>14 Oct 2024</td>
                            <td>$1300</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <div class="tab-pane fade" id="payment" role="tabpanel">
                <div class="card table-list-card mb-0">
                  <div
                    class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3"
                  >
                    <div class="search-set">
                      <div class="search-input">
                        <a href="javascript:void(0);" class="btn-searchset"
                          ><i class="ti ti-search fs-14 feather-search"></i
                        ></a>
                      </div>
                    </div>
                    <ul class="table-top-head">
                      <li>
                        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Pdf"
                          ><img src="@/assets/images/icons/pdf.svg" alt="img"
                        /></a>
                      </li>
                      <li>
                        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Excel"
                          ><img src="@/assets/images/icons/excel.svg" alt="img"
                        /></a>
                      </li>
                      <li>
                        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Print"
                          ><i class="ti ti-printer"></i
                        ></a>
                      </li>
                    </ul>
                  </div>
                  <div class="card-body">
                    <div class="custom-datatable-filter table-responsive">
                      <table class="table datatable">
                        <thead>
                          <tr>
                            <th class="no-sort">
                              <label class="checkboxs">
                                <input type="checkbox" class="select-all" />
                                <span class="checkmarks"></span>
                              </label>
                            </th>
                            <th>Customer</th>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th class="no-sort">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-27.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Carl Evans</a>
                              </div>
                            </td>
                            <td>INV/SL0101</td>
                            <td>24 Dec 2024</td>
                            <td>$1000</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-02.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Minerva Rameriz</a>
                              </div>
                            </td>
                            <td>INV/SL0102</td>
                            <td>10 Dec 2024</td>
                            <td>$1500</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-05.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Robert Lamon</a>
                              </div>
                            </td>
                            <td>INV/SL0103</td>
                            <td>27 Nov 2024</td>
                            <td>$1500</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-22.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Patricia Lewis</a>
                              </div>
                            </td>
                            <td>INV/SL0104</td>
                            <td>18 Nov 2024</td>
                            <td>$2000</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-03.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Mark Joslyn</a>
                              </div>
                            </td>
                            <td>INV/SL0105</td>
                            <td>06 Nov 2024</td>
                            <td>$800</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-12.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Marsha Betts</a>
                              </div>
                            </td>
                            <td>INV/SL0106</td>
                            <td>25 Oct 2024</td>
                            <td>$750</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-06.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Daniel Jude</a>
                              </div>
                            </td>
                            <td>INV/SL0107</td>
                            <td>14 Oct 2024</td>
                            <td>$1300</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <div class="tab-pane fade" id="return" role="tabpanel">
                <div class="card table-list-card mb-0">
                  <div
                    class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3"
                  >
                    <div class="search-set">
                      <div class="search-input">
                        <a href="javascript:void(0);" class="btn-searchset"
                          ><i class="ti ti-search fs-14 feather-search"></i
                        ></a>
                      </div>
                    </div>
                    <ul class="table-top-head">
                      <li>
                        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Pdf"
                          ><img src="@/assets/images/icons/pdf.svg" alt="img"
                        /></a>
                      </li>
                      <li>
                        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Excel"
                          ><img src="@/assets/images/icons/excel.svg" alt="img"
                        /></a>
                      </li>
                      <li>
                        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Print"
                          ><i class="ti ti-printer"></i
                        ></a>
                      </li>
                    </ul>
                  </div>
                  <div class="card-body">
                    <div class="custom-datatable-filter table-responsive">
                      <table class="table datatable">
                        <thead>
                          <tr>
                            <th class="no-sort">
                              <label class="checkboxs">
                                <input type="checkbox" class="select-all" />
                                <span class="checkmarks"></span>
                              </label>
                            </th>
                            <th>Customer</th>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th class="no-sort">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-27.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Carl Evans</a>
                              </div>
                            </td>
                            <td>INV/SL0101</td>
                            <td>24 Dec 2024</td>
                            <td>$1000</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-02.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Minerva Rameriz</a>
                              </div>
                            </td>
                            <td>INV/SL0102</td>
                            <td>10 Dec 2024</td>
                            <td>$1500</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-05.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Robert Lamon</a>
                              </div>
                            </td>
                            <td>INV/SL0103</td>
                            <td>27 Nov 2024</td>
                            <td>$1500</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-22.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Patricia Lewis</a>
                              </div>
                            </td>
                            <td>INV/SL0104</td>
                            <td>18 Nov 2024</td>
                            <td>$2000</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-03.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Mark Joslyn</a>
                              </div>
                            </td>
                            <td>INV/SL0105</td>
                            <td>06 Nov 2024</td>
                            <td>$800</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-12.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Marsha Betts</a>
                              </div>
                            </td>
                            <td>INV/SL0106</td>
                            <td>25 Oct 2024</td>
                            <td>$750</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <label class="checkboxs">
                                <input type="checkbox" />
                                <span class="checkmarks"></span>
                              </label>
                            </td>
                            <td>
                              <div class="d-flex align-items-center">
                                <a
                                  href="javascript:void(0);"
                                  class="avatar avatar-md me-2"
                                >
                                  <img
                                    :src="'/img/users/user-06.jpg'"
                                    alt="product"
                                  />
                                </a>
                                <a href="javascript:void(0);">Daniel Jude</a>
                              </div>
                            </td>
                            <td>INV/SL0107</td>
                            <td>14 Oct 2024</td>
                            <td>$1300</td>
                            <td class="action-table-data">
                              <div class="edit-delete-action">
                                <a class="me-2 edit-icon p-2" href="javascript:void(0);"
                                  ><i data-feather="eye" class="feather-eye"></i
                                ></a>
                                <a class="me-2 p-2" href="javascript:void(0);"
                                  ><i data-feather="edit" class="feather-edit"></i
                                ></a>
                                <a class="p-2" href="javascript:void(0);"
                                  ><i data-feather="trash-2" class="feather-trash-2"></i
                                ></a>
                              </div>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- /Recent Transactions -->

  <!-- Orders -->
  <div class="modal fade pos-modal" id="orders" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Orders</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="tabs-sets">
            <ul class="nav nav-tabs" id="myTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link active"
                  id="onhold-tab"
                  data-bs-toggle="tab"
                  data-bs-target="#onhold"
                  type="button"
                  aria-controls="onhold"
                  aria-selected="true"
                  role="tab"
                >
                  Onhold
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link"
                  id="unpaid-tab"
                  data-bs-toggle="tab"
                  data-bs-target="#unpaid"
                  type="button"
                  aria-controls="unpaid"
                  aria-selected="false"
                  role="tab"
                >
                  Unpaid
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button
                  class="nav-link"
                  id="paid-tab"
                  data-bs-toggle="tab"
                  data-bs-target="#paid"
                  type="button"
                  aria-controls="paid"
                  aria-selected="false"
                  role="tab"
                >
                  Paid
                </button>
              </li>
            </ul>
            <div class="tab-content">
              <div
                class="tab-pane fade show active"
                id="onhold"
                role="tabpanel"
                aria-labelledby="onhold-tab"
              >
                <div class="input-icon-start pos-search position-relative mb-3">
                  <span class="input-icon-addon">
                    <i class="ti ti-search"></i>
                  </span>
                  <input type="text" class="form-control" placeholder="Search Product" />
                </div>
                <div class="order-body">
                  <div class="card bg-light mb-3">
                    <div class="card-body">
                      <span class="badge bg-dark fs-12 mb-2">Order ID : #45698</span>
                      <div class="row g-3">
                        <div class="col-md-6">
                          <p class="fs-15 mb-1">
                            <span class="fs-14 fw-bold text-gray-9">Cashier :</span> admin
                          </p>
                          <p class="fs-15">
                            <span class="fs-14 fw-bold text-gray-9">Total :</span> $900
                          </p>
                        </div>
                        <div class="col-md-6">
                          <p class="fs-15 mb-1">
                            <span class="fs-14 fw-bold text-gray-9">Customer :</span>
                            Botsford
                          </p>
                          <p class="fs-15">
                            <span class="fs-14 fw-bold text-gray-9">Date :</span> 24 Dec
                            2024 13:39:11
                          </p>
                        </div>
                      </div>
                      <div class="bg-info-transparent p-1 rounded text-center my-3">
                        <p class="text-info fw-medium">
                          Customer need to recheck the product once
                        </p>
                      </div>
                      <div
                        class="d-flex align-items-center justify-content-center flex-wrap gap-2"
                      >
                        <a href="javascript:void(0);" class="btn btn-md btn-orange"
                          >Open Order</a
                        >
                        <a
                          href="javascript:void(0);"
                          class="btn btn-md btn-teal"
                          data-bs-dismiss="modal"
                          data-bs-toggle="modal"
                          data-bs-target="#products"
                          >View Products</a
                        >
                        <a href="javascript:void(0);" class="btn btn-md btn-indigo"
                          >Print</a
                        >
                      </div>
                    </div>
                  </div>
                  <div class="card bg-light mb-0">
                    <div class="card-body">
                      <span class="badge bg-dark fs-12 mb-2">Order ID : #666659</span>
                      <div class="mb-3">
                        <div class="row g-3">
                          <div class="col-md-6">
                            <p class="fs-15 mb-1">
                              <span class="fs-14 fw-bold text-gray-9">Cashier :</span>
                              admin
                            </p>
                            <p class="fs-15">
                              <span class="fs-14 fw-bold text-gray-9">Total :</span> $900
                            </p>
                          </div>
                          <div class="col-md-6">
                            <p class="fs-15 mb-1">
                              <span class="fs-14 fw-bold text-gray-9">Customer :</span>
                              Botsford
                            </p>
                            <p class="fs-15">
                              <span class="fs-14 fw-bold text-gray-9">Date :</span> 24 Dec
                              2024 13:39:11
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="tab-pane fade" id="unpaid" role="tabpanel">
                <div class="input-icon-start pos-search position-relative mb-3">
                  <span class="input-icon-addon">
                    <i class="ti ti-search"></i>
                  </span>
                  <input type="text" class="form-control" placeholder="Search Product" />
                </div>
                <div class="order-body">
                  <div class="card bg-light mb-3">
                    <div class="card-body">
                      <span class="badge bg-dark fs-12 mb-2">Order ID : #45698</span>
                      <div class="row g-3">
                        <div class="col-md-6">
                          <p class="fs-15 mb-1">
                            <span class="fs-14 fw-bold text-gray-9">Cashier :</span> admin
                          </p>
                          <p class="fs-15">
                            <span class="fs-14 fw-bold text-gray-9">Total :</span> $900
                          </p>
                        </div>
                        <div class="col-md-6">
                          <p class="fs-15 mb-1">
                            <span class="fs-14 fw-bold text-gray-9">Customer :</span>
                            Anastasia
                          </p>
                          <p class="fs-15">
                            <span class="fs-14 fw-bold text-gray-9">Date :</span> 24 Dec
                            2024 13:39:11
                          </p>
                        </div>
                      </div>
                      <div class="bg-info-transparent p-1 rounded text-center my-3">
                        <p class="text-info fw-medium">
                          Customer need to recheck the product once
                        </p>
                      </div>
                      <div
                        class="d-flex align-items-center justify-content-center flex-wrap gap-2"
                      >
                        <a href="javascript:void(0);" class="btn btn-md btn-orange"
                          >Open Order</a
                        >
                        <a
                          href="javascript:void(0);"
                          class="btn btn-md btn-teal"
                          data-bs-dismiss="modal"
                          data-bs-toggle="modal"
                          data-bs-target="#products"
                          >View Products</a
                        >
                        <a href="javascript:void(0);" class="btn btn-md btn-indigo"
                          >Print</a
                        >
                      </div>
                    </div>
                  </div>
                  <div class="card bg-light mb-0">
                    <div class="card-body">
                      <span class="badge bg-dark fs-12 mb-2">Order ID : #666659</span>
                      <div class="row g-3">
                        <div class="col-md-6">
                          <p class="fs-15 mb-1">
                            <span class="fs-14 fw-bold text-gray-9">Cashier :</span> admin
                          </p>
                          <p class="fs-15">
                            <span class="fs-14 fw-bold text-gray-9">Total :</span> $900
                          </p>
                        </div>
                        <div class="col-md-6">
                          <p class="fs-15 mb-1">
                            <span class="fs-14 fw-bold text-gray-9">Customer :</span>
                            Lucia
                          </p>
                          <p class="fs-15">
                            <span class="fs-14 fw-bold text-gray-9">Date :</span> 24 Dec
                            2024 13:39:11
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="tab-pane fade" id="paid" role="tabpanel">
                <div class="input-icon-start pos-search position-relative mb-3">
                  <span class="input-icon-addon">
                    <i class="ti ti-search"></i>
                  </span>
                  <input type="text" class="form-control" placeholder="Search Product" />
                </div>
                <div class="order-body">
                  <div class="card bg-light mb-3">
                    <div class="card-body">
                      <span class="badge bg-dark fs-12 mb-2">Order ID : #45698</span>
                      <div class="row g-3">
                        <div class="col-md-6">
                          <p class="fs-15 mb-1">
                            <span class="fs-14 fw-bold text-gray-9">Cashier :</span> admin
                          </p>
                          <p class="fs-15">
                            <span class="fs-14 fw-bold text-gray-9">Total :</span> $1000
                          </p>
                        </div>
                        <div class="col-md-6">
                          <p class="fs-15 mb-1">
                            <span class="fs-14 fw-bold text-gray-9">Customer :</span> Hugo
                          </p>
                          <p class="fs-15">
                            <span class="fs-14 fw-bold text-gray-9">Date :</span> 24 Dec
                            2024 13:39:11
                          </p>
                        </div>
                      </div>
                      <div class="bg-info-transparent p-1 rounded text-center my-3">
                        <p class="text-info fw-medium">
                          Customer need to recheck the product once
                        </p>
                      </div>
                      <div
                        class="d-flex align-items-center justify-content-center flex-wrap gap-2"
                      >
                        <a href="javascript:void(0);" class="btn btn-md btn-orange"
                          >Open Order</a
                        >
                        <a
                          href="javascript:void(0);"
                          class="btn btn-md btn-teal"
                          data-bs-dismiss="modal"
                          data-bs-toggle="modal"
                          data-bs-target="#products"
                          >View Products</a
                        >
                        <a href="javascript:void(0);" class="btn btn-md btn-indigo"
                          >Print</a
                        >
                      </div>
                    </div>
                  </div>
                  <div class="card bg-light mb-0">
                    <div class="card-body">
                      <span class="badge bg-dark fs-12 mb-2">Order ID : #666659</span>
                      <div class="row g-3">
                        <div class="col-md-6">
                          <p class="fs-15 mb-1">
                            <span class="fs-14 fw-bold text-gray-9">Cashier :</span> admin
                          </p>
                          <p class="fs-15">
                            <span class="fs-14 fw-bold text-gray-9">Total :</span> $9100
                          </p>
                        </div>
                        <div class="col-md-6">
                          <p class="fs-15 mb-1">
                            <span class="fs-14 fw-bold text-gray-9">Customer :</span>
                            Antonio
                          </p>
                          <p class="fs-15">
                            <span class="fs-14 fw-bold text-gray-9">Date :</span> 23 Dec
                            2024 13:39:11
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- /Orders -->

  <!-- Scan -->
  <div class="modal fade modal-default" id="scan-payment">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
          <div class="success-wrap scan-wrap text-center">
							<h5><span class="text-gray-6">Amount to Pay :</span> $150</h5>
							<div class="scan-img">
								<img :src="'/img/icons/scan-img.svg'" class="scan-image" alt="img">
								<img :src="'/img/icons/scan-img-white.svg'" class="scan-img-white" alt="img">
							</div>
							<p class="mb-3">Scan your Phone or UPI App to Complete the payment</p>
						</div>
        </div>
      </div>
    </div>
  </div>
  <!-- /Scan -->

  <!-- Payment Cash -->
  <div class="modal fade modal-default" id="payment-cash">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Finalize Sale</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <form @submit.prevent="submitForm">
          <div class="modal-body pb-1">
            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label"
                    >Received Amount <span class="text-danger">*</span></label
                  >
                  <div class="input-icon-start position-relative">
                    <span class="input-icon-addon text-gray-9">
                      <i class="ti ti-currency-dollar"></i>
                    </span>
                    <input type="text" class="form-control" value="1800" />
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label"
                    >Paying Amount <span class="text-danger">*</span></label
                  >
                  <div class="input-icon-start position-relative">
                    <span class="input-icon-addon text-gray-9">
                      <i class="ti ti-currency-dollar"></i>
                    </span>
                    <input type="text" class="form-control" value="1800" />
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="change-item mb-3">
                  <label class="form-label">Change</label>
                  <div class="input-icon-start position-relative">
                    <span class="input-icon-addon text-gray-9">
                      <i class="ti ti-currency-dollar"></i>
                    </span>
                    <input type="text" class="form-control" value="0.00" />
                  </div>
                </div>
                <div class="point-item mb-3">
                  <label class="form-label">Balance Point</label>
                  <input type="text" class="form-control" value="200" />
                </div>
              </div>
              <div class="col-md-12">
                <div class="mb-3">
                  <label class="form-label"
                    >Payment Type <span class="text-danger">*</span></label
                  >
                  <vue-select
                    :options="Payment"
                    v-model="selectedFour"
                    placeholder="Credit Card"
                  />
                </div>
                <div class="quick-cash payment-content bg-light mb-3">
                  <div class="d-flex align-items-center flex-wra gap-4">
                    <h5 class="text-nowrap">Quick Cash</h5>
                    <div class="d-flex align-items-center flex-wrap gap-3">
                      <div class="form-check">
                        <input
                          type="radio"
                          class="btn-check"
                          name="cash"
                          id="cash21"
                          checked
                        />
                        <label class="btn btn-white" for="cash21">10</label>
                      </div>
                      <div class="form-check">
                        <input type="radio" class="btn-check" name="cash" id="cash22" />
                        <label class="btn btn-white" for="cash22">20</label>
                      </div>
                      <div class="form-check">
                        <input type="radio" class="btn-check" name="cash" id="cash23" />
                        <label class="btn btn-white" for="cash23">50</label>
                      </div>
                      <div class="form-check">
                        <input type="radio" class="btn-check" name="cash" id="cash24" />
                        <label class="btn btn-white" for="cash24">100</label>
                      </div>
                      <div class="form-check">
                        <input type="radio" class="btn-check" name="cash" id="cash25" />
                        <label class="btn btn-white" for="cash25">500</label>
                      </div>
                      <div class="form-check">
                        <input type="radio" class="btn-check" name="cash" id="cash26" />
                        <label class="btn btn-white" for="cash26">1000</label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="point-wrap payment-content mb-3">
                  <div
                    class="bg-success-transparent d-flex align-items-center justify-content-between flex-wrap p-2 gap-2 br-5"
                  >
                    <h6 class="fs-14 fw-bold text-success">
                      You have 2000 Points to Use
                    </h6>
                    <a href="javascript:void(0);" class="btn btn-dark btn-md"
                      >Use for this Purchase</a
                    >
                  </div>
                </div>
              </div>
              <div class="col-md-12">
                <div class="mb-3">
                  <label class="form-label">Payment Receiver</label>
                  <input type="text" class="form-control" />
                </div>
              </div>
              <div class="col-md-12">
                <div class="mb-3">
                  <label class="form-label">Payment Note</label>
                  <textarea
                    class="form-control"
                    rows="3"
                    placeholder="Type your message"
                  ></textarea>
                </div>
              </div>
              <div class="col-md-12">
                <div class="mb-3">
                  <label class="form-label">Sale Note</label>
                  <textarea
                    class="form-control"
                    rows="3"
                    placeholder="Type your message"
                  ></textarea>
                </div>
              </div>
              <div class="col-md-12">
                <div class="mb-3">
                  <label class="form-label">Staff Note</label>
                  <textarea
                    class="form-control"
                    rows="3"
                    placeholder="Type your message"
                  ></textarea>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-end flex-wrap gap-2">
            <button
              type="button"
              class="btn btn-md btn-secondary"
              data-bs-dismiss="modal"
            >
              Cancel
            </button>
            <button type="submit" class="btn btn-md btn-primary">Submit</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- /Payment Cash  -->

  <!-- Payment Card  -->
  <div class="modal fade modal-default" id="payment-card">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Finalize Sale</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <form @submit.prevent="submitForm">
          <div class="modal-body pb-1">
            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label"
                    >Received Amount <span class="text-danger">*</span></label
                  >
                  <div class="input-icon-start position-relative">
                    <span class="input-icon-addon text-gray-9">
                      <i class="ti ti-currency-dollar"></i>
                    </span>
                    <input type="text" class="form-control" value="1800" />
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label"
                    >Paying Amount <span class="text-danger">*</span></label
                  >
                  <div class="input-icon-start position-relative">
                    <span class="input-icon-addon text-gray-9">
                      <i class="ti ti-currency-dollar"></i>
                    </span>
                    <input type="text" class="form-control" value="1800" />
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="change-item mb-3">
                  <label class="form-label">Change</label>
                  <div class="input-icon-start position-relative">
                    <span class="input-icon-addon text-gray-9">
                      <i class="ti ti-currency-dollar"></i>
                    </span>
                    <input type="text" class="form-control" value="0.00" />
                  </div>
                </div>
                <div class="point-item mb-3">
                  <label class="form-label">Balance Point</label>
                  <input type="text" class="form-control" value="200" />
                </div>
              </div>
              <div class="col-md-12">
                <div class="mb-3">
                  <label class="form-label"
                    >Payment Type <span class="text-danger">*</span></label
                  >
                  <vue-select
                    :options="Payment"
                    v-model="selectedFive"
                    placeholder="Credit Card"
                  />
                </div>
                <div class="quick-cash payment-content bg-light mb-3">
                  <div class="d-flex align-items-center flex-wra gap-4">
                    <h5 class="text-nowrap">Quick Cash</h5>
                    <div class="d-flex align-items-center flex-wrap gap-3">
                      <div class="form-check">
                        <input
                          type="radio"
                          class="btn-check"
                          name="cash"
                          id="cash11"
                          checked
                        />
                        <label class="btn btn-white" for="cash11">10</label>
                      </div>
                      <div class="form-check">
                        <input type="radio" class="btn-check" name="cash" id="cash12" />
                        <label class="btn btn-white" for="cash12">20</label>
                      </div>
                      <div class="form-check">
                        <input type="radio" class="btn-check" name="cash" id="cash13" />
                        <label class="btn btn-white" for="cash13">50</label>
                      </div>
                      <div class="form-check">
                        <input type="radio" class="btn-check" name="cash" id="cash14" />
                        <label class="btn btn-white" for="cash14">100</label>
                      </div>
                      <div class="form-check">
                        <input type="radio" class="btn-check" name="cash" id="cash15" />
                        <label class="btn btn-white" for="cash15">500</label>
                      </div>
                      <div class="form-check">
                        <input type="radio" class="btn-check" name="cash" id="cash16" />
                        <label class="btn btn-white" for="cash16">1000</label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="point-wrap payment-content mb-3">
                  <div
                    class="bg-success-transparent d-flex align-items-center justify-content-between flex-wrap p-2 gap-2 br-5"
                  >
                    <h6 class="fs-14 fw-bold text-success">
                      You have 2000 Points to Use
                    </h6>
                    <a href="javascript:void(0);" class="btn btn-dark btn-md"
                      >Use for this Purchase</a
                    >
                  </div>
                </div>
              </div>
              <div class="col-md-12">
                <div class="mb-3">
                  <label class="form-label">Payment Receiver</label>
                  <input type="text" class="form-control" />
                </div>
              </div>
              <div class="col-md-12">
                <div class="mb-3">
                  <label class="form-label">Payment Note</label>
                  <textarea
                    class="form-control"
                    rows="3"
                    placeholder="Type your message"
                  ></textarea>
                </div>
              </div>
              <div class="col-md-12">
                <div class="mb-3">
                  <label class="form-label">Sale Note</label>
                  <textarea
                    class="form-control"
                    rows="3"
                    placeholder="Type your message"
                  ></textarea>
                </div>
              </div>
              <div class="col-md-12">
                <div class="mb-3">
                  <label class="form-label">Staff Note</label>
                  <textarea
                    class="form-control"
                    rows="3"
                    placeholder="Type your message"
                  ></textarea>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-end flex-wrap gap-2">
            <button
              type="button"
              class="btn btn-md btn-secondary"
              data-bs-dismiss="modal"
            >
              Cancel
            </button>
            <button type="submit" class="btn btn-md btn-primary">Submit</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- /Payment Card  -->

<!-- Calculator -->
<div class="modal fade pos-modal" id="calculator" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-body p-0">
          <div class="calculator-wrap">
            <div class="p-3">
              <div class="d-flex align-items-center">
                <h3>Calculator</h3>
                <button
                  type="button"
                  class="close"
                  data-bs-dismiss="modal"
                  aria-label="Close"
                >
                  <span aria-hidden="true">×</span>
                </button>
              </div>
              <div>
                <input 
                  class="input" 
                  type="text" 
                  :value="formattedDisplay" 
                  placeholder="0" 
                  readonly 
                />
              </div>
            </div>
            <div class="calculator-body d-flex justify-content-between">
              <div class="text-center">
                <button class="btn btn-clear" @click="clr()">C</button>
                <button class="btn btn-number" @click="dis('7')">7</button>
                <button class="btn btn-number" @click="dis('4')">4</button>
                <button class="btn btn-number" @click="dis('1')">1</button>
                <button class="btn btn-number" @click="dis(',')">,</button>
              </div>
              <div class="text-center">
                <button class="btn btn-expression" @click="dis('/')">÷</button>
                <button class="btn btn-number" @click="dis('8')">8</button>
                <button class="btn btn-number" @click="dis('5')">5</button>
                <button class="btn btn-number" @click="dis('2')">2</button>
                <button class="btn btn-number" @click="dis('00')">00</button>
              </div>
              <div class="text-center">
                <button class="btn btn-expression" @click="dis('%')">%</button>
                <button class="btn btn-number" @click="dis('9')">9</button>
                <button class="btn btn-number" @click="dis('6')">6</button>
                <button class="btn btn-number" @click="dis('3')">3</button>
                <button class="btn btn-number" @click="dis('.')">.</button>
              </div>
              <div class="text-center">
                <button class="btn btn-clear" @click="back()">
                  <i class="ti ti-backspace"></i>
                </button>
                <button class="btn btn-expression" @click="dis('*')">x</button>
                <button class="btn btn-expression" @click="dis('-')">-</button>
                <button class="btn btn-expression" @click="dis('+')">+</button>
                <button class="btn btn-clear" @click="solve()">=</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- /Calculator -->

  <!-- Cash Register Details -->
  <div class="modal fade pos-modal" id="cash-register" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Cash Register Details</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table table-striped border">
              <tbody>
                <tr>
                  <td>Cash in Hand</td>
                  <td class="text-gray-9 fw-medium text-end">$45689</td>
                </tr>
                <tr>
                  <td>Total Sale Amount</td>
                  <td class="text-gray-9 fw-medium text-end">$565597.88</td>
                </tr>
                <tr>
                  <td>Total Payment</td>
                  <td class="text-gray-9 fw-medium text-end">$566867.97</td>
                </tr>
                <tr>
                  <td>Cash Payment</td>
                  <td class="text-gray-9 fw-medium text-end">$3355.84</td>
                </tr>
                <tr>
                  <td>Total Sale Return</td>
                  <td class="text-gray-9 fw-medium text-end">$1959</td>
                </tr>
                <tr>
                  <td>Total Expense</td>
                  <td class="text-gray-9 fw-medium text-end">$0</td>
                </tr>
                <tr>
                  <td class="text-gray-9 fw-bold bg-secondary-transparent">Total Cash</td>
                  <td class="text-gray-9 fw-bold text-end bg-secondary-transparent">
                    $587130.97
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2 flex-wrap">
          <button type="button" class="btn btn-md btn-primary" data-bs-dismiss="modal">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
  <!-- /Cash Register Details -->

  <!-- Today's Sale -->
  <div class="modal fade pos-modal" id="today-sale" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Today's Sale</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
            <table class="table table-striped border">
              <tbody>
                <tr>
                  <td>Total Sale Amount</td>
                  <td class="text-gray-9 fw-medium text-end">$565597.88</td>
                </tr>
                <tr>
                  <td>Cash Payment</td>
                  <td class="text-gray-9 fw-medium text-end">$3355.84</td>
                </tr>
                <tr>
                  <td>Credit Card Payment</td>
                  <td class="text-gray-9 fw-medium text-end">$1959</td>
                </tr>
                <tr>
                  <td>Cheque Payment:</td>
                  <td class="text-gray-9 fw-medium text-end">$0</td>
                </tr>
                <tr>
                  <td>Deposit Payment</td>
                  <td class="text-gray-9 fw-medium text-end">$565597.88</td>
                </tr>
                <tr>
                  <td>Points Payment</td>
                  <td class="text-gray-9 fw-medium text-end">$3355.84</td>
                </tr>
                <tr>
                  <td>Gift Card Payment</td>
                  <td class="text-gray-9 fw-medium text-end">$565597.88</td>
                </tr>
                <tr>
                  <td>Scan & Pay</td>
                  <td class="text-gray-9 fw-medium text-end">$3355.84</td>
                </tr>
                <tr>
                  <td>Pay Later</td>
                  <td class="text-gray-9 fw-medium text-end">$3355.84</td>
                </tr>
                <tr>
                  <td>Total Payment</td>
                  <td class="text-gray-9 fw-medium text-end">$565597.88</td>
                </tr>
                <tr>
                  <td>Total Sale Return</td>
                  <td class="text-gray-9 fw-medium text-end">$565597.88</td>
                </tr>
                <tr>
                  <td>Total Expense:</td>
                  <td class="text-gray-9 fw-medium text-end">$565597.88</td>
                </tr>
                <tr>
                  <td class="text-gray-9 fw-bold bg-secondary-transparent">Total Cash</td>
                  <td class="text-gray-9 fw-bold text-end bg-secondary-transparent">
                    $587130.97
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2 flex-wrap">
          <button type="button" class="btn btn-md btn-primary" data-bs-dismiss="modal">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
  <!-- /Today's Sale -->

  <!-- Today's Profit -->
  <div class="modal fade pos-modal" id="today-profit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Today's Profit</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="row justify-content-center g-3 mb-3">
            <div class="col-lg-4 col-md-6 d-flex">
              <div
                class="border border-success bg-success-transparent br-8 p-3 flex-fill"
              >
                <p class="fs-16 text-gray-9 mb-1">Total Sale</p>
                <h3 class="text-success">$89954</h3>
              </div>
            </div>
            <div class="col-lg-4 col-md-6 d-flex">
              <div class="border border-danger bg-danger-transparent br-8 p-3 flex-fill">
                <p class="fs-16 text-gray-9 mb-1">Expense</p>
                <h3 class="text-danger">$89954</h3>
              </div>
            </div>
            <div class="col-lg-4 col-md-6 d-flex">
              <div class="border border-info bg-info-transparent br-8 p-3 flex-fill">
                <p class="fs-16 text-gray-9 mb-1">Total Profit</p>
                <h3 class="text-info">$2145</h3>
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-striped border">
              <tbody>
                <tr>
                  <td>Product Revenue</td>
                  <td class="text-gray-9 fw-medium text-end">$565597.88</td>
                </tr>
                <tr>
                  <td>Product Cost</td>
                  <td class="text-gray-9 fw-medium text-end">$3355.84</td>
                </tr>
                <tr>
                  <td>Expense</td>
                  <td class="text-gray-9 fw-medium text-end">$1959</td>
                </tr>
                <tr>
                  <td>Total Stock Adjustment</td>
                  <td class="text-gray-9 fw-medium text-end">$0</td>
                </tr>
                <tr>
                  <td>Deposit Payment</td>
                  <td class="text-gray-9 fw-medium text-end">$565597.88</td>
                </tr>
                <tr>
                  <td>Total Purchase Shipping Cost</td>
                  <td class="text-gray-9 fw-medium text-end">$3355.84</td>
                </tr>
                <tr>
                  <td>Total Sell Discount</td>
                  <td class="text-gray-9 fw-medium text-end">$565597.88</td>
                </tr>
                <tr>
                  <td>Total Sell Return</td>
                  <td class="text-gray-9 fw-medium text-end">$3355.84</td>
                </tr>
                <tr>
                  <td>Closing Stock</td>
                  <td class="text-gray-9 fw-medium text-end">$3355.84</td>
                </tr>
                <tr>
                  <td>Total Sales</td>
                  <td class="text-gray-9 fw-medium text-end">$565597.88</td>
                </tr>
                <tr>
                  <td>Total Sale Return</td>
                  <td class="text-gray-9 fw-medium text-end">$565597.88</td>
                </tr>
                <tr>
                  <td>Total Expense</td>
                  <td class="text-gray-9 fw-medium text-end">$565597.88</td>
                </tr>
                <tr>
                  <td class="text-gray-9 fw-bold bg-secondary-transparent">Total Cash</td>
                  <td class="text-gray-9 fw-bold text-end bg-secondary-transparent">
                    $587130.97
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2 flex-wrap">
          <button type="button" class="btn btn-md btn-primary" data-bs-dismiss="modal">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
  <!-- /Today's Profit -->
</template>

<script>
export default {
  data() {
    return {
      calculatorDisplay: "0",
      selected: "Exclusive",
      selectedTwo: "Percentage",
      selectedThree: "Kilogram",
      selectedFour: "Kilogram",
      selectedFive: "Kilogram",
      Taxtype: [
        {label: "Exclusive", value: "Exclusive"}, 
        {label: "Inclusive", value: "Inclusive"}, 
      ],
      Discount: [
        {label: "Percentage", value: "Percentage"}, 
        {label: "Early Payment Discounts", value: "Early Payment Discounts"},
      ],
      Unit: [
        {label: "Kilogram", value: "Kilogram"}, 
        {label: "Grams", value: "Grams"}, 
      ],
      Payment: [
        {label: "Credit Card", value: "Credit Card"}, 
        {label: "Cash", value: "Cash"}, 
        {label: "Cheque", value: "Cheque"}, 
        {label: "Deposit", value: "Deposit"}, 
        {label: "Points", value: "Points"},
      ],
    };
  },
  computed: {
    formattedDisplay() {
      return this.calculatorDisplay.replace(/\*/g, "×");
    },
  },
  methods: {
    submitForm() {
      this.$router.push("/pos/pos-2");
    },
    clr() {
      this.calculatorDisplay = "0";
    },
    dis(value) {
      // Reset if error state
      if (this.calculatorDisplay === "Error") {
        this.calculatorDisplay = value === "." ? "0." : value;
        return;
      }
      
      // Handle operators - reset if last character is an operator
      const operators = ["+", "-", "*", "/", "%"];
      const lastChar = this.calculatorDisplay.slice(-1);
      
      if (this.calculatorDisplay === "0" && value !== ".") {
        this.calculatorDisplay = value;
      } else if (operators.includes(lastChar) && operators.includes(value)) {
        // Replace the last operator with new one
        this.calculatorDisplay = this.calculatorDisplay.slice(0, -1) + value;
      } else {
        this.calculatorDisplay += value;
      }
    },
    back() {
      if (this.calculatorDisplay === "Error") {
        this.calculatorDisplay = "0";
        return;
      }
      
      if (this.calculatorDisplay.length > 1) {
        this.calculatorDisplay = this.calculatorDisplay.slice(0, -1);
      } else {
        this.calculatorDisplay = "0";
      }
    },
    solve() {
      try {
        if (this.calculatorDisplay === "Error") {
          this.calculatorDisplay = "0";
          return;
        }
        
        let expression = this.calculatorDisplay;
        
        // Remove commas (thousand separators)
        expression = expression.replace(/,/g, "");
        
        // Replace display symbols with JavaScript operators
        expression = expression.replace(/×/g, "*");
        expression = expression.replace(/÷/g, "/");
        
        // Handle percentage
        if (expression.includes("%")) {
          const parts = expression.split("%");
          if (parts.length === 2 && parts[1].trim() === "") {
            const number = parseFloat(parts[0]);
            if (!isNaN(number)) {
              const result = number / 100;
              this.calculatorDisplay = this.formatNumber(result);
              return;
            }
          }
        }
        
        // Validate expression doesn't end with operator
        const lastChar = expression.trim().slice(-1);
        if (["+", "-", "*", "/"].includes(lastChar)) {
          expression = expression.slice(0, -1);
        }
        
        if (!expression || expression.trim() === "") {
          this.calculatorDisplay = "0";
          return;
        }
        
        // Evaluate the expression safely
        const result = Function(`"use strict"; return (${expression})`)();
        
        // Format the result
        if (isNaN(result) || !isFinite(result)) {
          this.calculatorDisplay = "Error";
        } else {
          this.calculatorDisplay = this.formatNumber(result);
        }
      } catch (error) {
        this.calculatorDisplay = "Error";
      }
    },
    formatNumber(num) {
      // Round to avoid floating point precision issues
      const rounded = Math.round(num * 100000000) / 100000000;
      return rounded.toString();
    },
  },
};
</script>
