<template>
      <div>
            <PageHeader title="Financial Settings" subtitle="Manage your financial settings" />
      </div>

      <div class="row">
            <div class="col-xl-12">
                  <div class="settings-wrapper d-flex">
                        <settings-sidebar></settings-sidebar>
                        <div class="card flex-fill mb-0">
                              <div class="card-header">
                                    <h4 class="fs-18 fw-bold">Tax Rates</h4>
                              </div>
                              <div class="card-body">
                                    <div class="table-responsive">
                                          <table class="table border">
                                                <thead class="thead-light">
                                                      <tr>
                                                            <th>Tax Name</th>
                                                            <th>Tax rates</th>
                                                            <th>Created On</th>
                                                            <th class="no-sort"></th>
                                                      </tr>
                                                </thead>
                                                <tbody>
                                                      <tr>
                                                            <td>VAT</td>
                                                            <td>10%</td>
                                                            <td>12 Jan 2025</td>
                                                            <td class="action-table-data justify-content-end">
                                                                  <div class="edit-delete-action">
                                                                        <a class="me-2 p-2" href="#"
                                                                              data-bs-toggle="modal"
                                                                              data-bs-target="#edit-tax">
                                                                              <i data-feather="edit"
                                                                                    class="feather-edit"></i>
                                                                        </a>
                                                                        <a class="p-2" href="javascript:void(0);"
                                                                              data-bs-toggle="modal"
                                                                              data-bs-target="#delete-modal">
                                                                              <i data-feather="trash-2"
                                                                                    class="feather-trash-2"></i>
                                                                        </a>
                                                                  </div>
                                                            </td>
                                                      </tr>
                                                      <tr>
                                                            <td>CGST</td>
                                                            <td>08%</td>
                                                            <td>10 Jan 2025</td>
                                                            <td class="action-table-data justify-content-end">
                                                                  <div class="edit-delete-action">
                                                                        <a class="me-2 p-2" href="#"
                                                                              data-bs-toggle="modal"
                                                                              data-bs-target="#edit-tax">
                                                                              <i data-feather="edit"
                                                                                    class="feather-edit"></i>
                                                                        </a>
                                                                        <a class="p-2" href="javascript:void(0);"
                                                                              data-bs-toggle="modal"
                                                                              data-bs-target="#delete-modal">
                                                                              <i data-feather="trash-2"
                                                                                    class="feather-trash-2"></i>
                                                                        </a>
                                                                  </div>
                                                            </td>
                                                      </tr>
                                                      <tr>
                                                            <td>SGST</td>
                                                            <td>10%</td>
                                                            <td>06 Jan 2025</td>
                                                            <td class="action-table-data justify-content-end">
                                                                  <div class="edit-delete-action">
                                                                        <a class="me-2 p-2" href="#"
                                                                              data-bs-toggle="modal"
                                                                              data-bs-target="#edit-tax">
                                                                              <i data-feather="edit"
                                                                                    class="feather-edit"></i>
                                                                        </a>
                                                                        <a class="p-2" href="javascript:void(0);"
                                                                              data-bs-toggle="modal"
                                                                              data-bs-target="#delete-modal">
                                                                              <i data-feather="trash-2"
                                                                                    class="feather-trash-2"></i>
                                                                        </a>
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
</template>