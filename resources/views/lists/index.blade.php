@extends('layouts.core.frontend', [
    'menu' => 'list',
])

@section('title', trans('messages.my_lists'))

@section('page_header')

    <div class="page-title">
        <ul class="breadcrumb breadcrumb-caret position-right">
            <li class="breadcrumb-item"><a href="{{ action('HomeController@index') }}">{{ trans('messages.home') }}</a></li>
        </ul>
        <h1>
            <span class="text-semibold"><span class="material-symbols-rounded">format_list_bulleted</span>
                {{ trans('messages.my_lists') }}</span>
        </h1>
    </div>

@endsection

@section('content')
    <div class="listing-form" id="ListsIndexContainer">
        <div class="d-flex top-list-controls top-sticky-content">
            <div class="me-auto">
                @if (Auth::user()->customer->listsCount() >= 0)
                    <div class="filter-box">
                        <div class="checkbox inline check_all_list">
                            <label>
                                <input type="checkbox" name="page_checked" class="styled check_all">
                            </label>
                        </div>
                        <div class="dropdown list_actions" style="display: none">
                            <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                {{ trans('messages.actions') }} <span class="number"></span><span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item"
                                        link-confirm-url="{{ action('MailListController@deleteConfirm') }}"
                                        link-method="POST" href="{{ action('MailListController@delete') }}">
                                        <span class="material-symbols-rounded">delete_outline</span>
                                        {{ trans('messages.delete') }}</a>
                                </li>
                            </ul>
                        </div>
                        <span class="filter-group">
                            <span class="title text-semibold text-muted">{{ trans('messages.sort_by') }}</span>
                            <select class="select" name="sort_order">
                                <option value="created_at">{{ trans('messages.created_at') }}</option>
                                <option value="name">{{ trans('messages.name') }}</option>
                            </select>

                            <input type="hidden" name="sort_direction" value="desc" />
                            <button type="button" class="btn btn-light sort-direction" data-popup="tooltip"
                                title="{{ trans('messages.change_sort_direction') }}" role="button" class="btn btn-xs">
                                <span class="material-symbols-rounded">sort</span>
                            </button>
                        </span>
                        <span class="text-nowrap">
                            <input type="text" name="keyword" class="form-control search"
                                value="{{ request()->keyword }}" placeholder="{{ trans('messages.type_to_search') }}" />
                            <span class="material-symbols-rounded">search</span>
                        </span>
                    </div>
                @endif
            </div>
            <div class="text-end">
                @if (Auth::user()->customer->can('create', new Acelle\Model\MailList()))
                    <a href="{{ action('MailListController@create') }}" role="button" class="btn btn-secondary">
                        <span class="material-symbols-rounded">add</span> {{ trans('messages.create_list') }}
                    </a>
                    <button id="syncMailListsButton" class="btn btn-secondary">
                        <span class="material-symbols-rounded sync-icon">sync</span>
                        <span class="btn-text">{{ trans('messages.sync_mail_list') }}</span>
                        <div class="spinner-border spinner-border-sm me-1 d-none" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </button>
                @endif
            </div>
        </div>

        <div id="ListsIndexContent"></div>
    </div>

    <script>
        var ListsIndex = {
            list: null,
            getList: function() {
                if (this.list == null) {
                    this.list = makeList({
                        url: '{{ action('MailListController@listing') }}',
                        container: $('#ListsIndexContainer'),
                        content: $('#ListsIndexContent')
                    });
                }
                return this.list;
            }
        };

        $(document).ready(function() {
            ListsIndex.getList().load();
            $('#syncMailListsButton').attr('data-syncing', 'false');

            $('#syncMailListsButton').click(function() {
                const button = $(this);

                // Prevent double-submission
                if (button.attr('data-syncing') === 'true') {
                    return;
                }

                button.attr('data-syncing', 'true');
                button.prop('disabled', true);

                // Show spinner inside button
                button.find('.spinner-border').removeClass('d-none');
                button.find('.sync-icon').addClass('d-none');

                // Show loading overlay
                $('#loadingOverlay').fadeIn();

                $.ajax({
                    url: '/lists/sync',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        notify({
                            type: 'success',
                            message: response.message
                        });

                        // Poll for sync status (optional enhancement)
                        // pollSyncStatus();
                    },
                    error: function(xhr) {
                        let errorMessage = 'An error occurred while syncing mail lists.';

                        try {
                            errorMessage = xhr.responseJSON?.message || errorMessage;
                        } catch (e) {
                            console.error('Error parsing error response:', e);
                        }

                        notify({
                            type: 'error',
                            message: errorMessage
                        });
                    },
                    complete: function() {
                        button.attr('data-syncing', 'false');
                        button.prop('disabled', false);
                        button.find('.spinner-border').addClass('d-none');
                        button.find('.sync-icon').removeClass('d-none');
                        $('#loadingOverlay').fadeOut();
                    }
                });
            });
        });
    </script>
@endsection
