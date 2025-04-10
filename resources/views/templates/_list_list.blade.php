@if ($templates->count() > 0)
    <table class="table table-box pml-table mt-2"
        current-page="{{ empty(request()->page) ? 1 : empty(request()->page) }}">
        @foreach ($templates as $key => $template)
            <tr class="position-relative">
                <td width="1%" class="list-check-col">
                    <div class="text-nowrap">
                        <div class="checkbox inline me-1">
                            <label>
                                <input type="checkbox" class="node styled" name="uids[]" value="{{ $template->uid }}" />
                            </label>
                        </div>
                    </div>
                </td>
                <td width="1%">
                    <a href="#preview"
                        onclick="popupwindow('{{ action('TemplateController@preview', $template->uid) }}', `{{ $template->name }}`, 800)">
                        <img class="template-thumb rounded shadow-sm" width="100" height="128"
                            src="{{ $template->getThumbUrl() }}?v={{ rand(0, 10) }}" />
                    </a>
                </td>
                <td>
                    <a class="kq_search fw-600 d-block list-title" href="#preview"
                        onclick="popupwindow('{{ action('TemplateController@preview', $template->uid) }}', `{{ $template->name }}`, 800)">
                        {{ $template->name }}
                    </a>
                    <div class="text-muted">
                        {!! $template->admin ? '<i class="icon-user-tie" data="admin"></i>' . $template->admin->displayName() : '' !!}
                        {!! $template->customer ? '<i class="icon-user" data="customer"></i>' . $template->customer->displayName() : '' !!}
                    </div>
                    <span class="text-muted">{{ trans('messages.updated_at') }}:
                        {{ Auth::user()->customer->formatDateTime($template->created_at, 'datetime_full') }}</span>
                </td>

                <td>
                    <div class="single-stat-box pull-left">
                        @if ($template->categories()->count())
                            <span
                                class="template-categories">{{ $template->categories->map(function ($cat) {
                                        return $cat->name;
                                    })->join(', ') }}</span>
                            <br>
                            <span class="text-muted text-nowrap">{{ trans('messages.template.category') }}</span>
                        @else
                            <span class="text-muted2">{{ trans('messages.template.category.no_category') }}</span>
                        @endif

                    </div>
                </td>

                <td class="text-end pe-0">
                    @if (Auth::user()->customer->can('update', $template))
                        {{-- @if (in_array(Acelle\Model\Setting::get('builder'), ['both', 'pro']) && $template->builder) --}}
                        <a href="{{ action('TemplateController@builderEdit', $template->uid) }}" role="button"
                            class="btn btn-primary btn-icon template-compose">
                            {{ trans('messages.template.pro_builder') }}
                        </a>
                        {{-- @endif --}}
                        {{-- @if (in_array(Acelle\Model\Setting::get('builder'), ['both', 'classic'])) --}}
                        <a href="{{ action('TemplateController@edit', $template->uid) }}" role="button"
                            class="btn btn-secondary btn-icon template-compose-classic">
                            {{ trans('messages.template.classic_builder') }}
                        </a>
                        {{-- @endif --}}
                    @endif
                    @if (Auth::user()->customer->can('preview', $template) ||
                            Auth::user()->customer->can('copy', $template) ||
                            Auth::user()->customer->can('delete', $template) ||
                            Auth::user()->customer->can('update', $template))
                        <div class="btn-group">
                            <button role="button" class="btn btn-light dropdown-toggle"
                                data-bs-toggle="dropdown"></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                @if (Auth::user()->customer->can('update', $template))
                                    <li>
                                        <a class="dropdown-item change-template-name"
                                            href="{{ action('TemplateController@changeName', [
                                                'uid' => $template->uid,
                                            ]) }}">
                                            <span class="material-symbols-rounded">subtitles</span>
                                            {{ trans('messages.template.change_name') }}
                                        </a>
                                    </li>
                                @endif
                                @if (Auth::user()->customer->can('preview', $template))
                                    <li><a class="dropdown-item" href="#preview"
                                            onclick="popupwindow('{{ action('TemplateController@preview', $template->uid) }}', `{{ $template->name }}`, 800)"><span
                                                class="material-symbols-rounded">zoom_in</span>
                                            {{ trans('messages.preview') }}</a></li>
                                @endif
                                @if (Auth::user()->customer->can('update', $template))
                                    <li>
                                        <a class="dropdown-item upload-thumb-button"
                                            href="{{ action('TemplateController@updateThumb', $template->uid) }}">
                                            <span class="material-symbols-rounded">insert_photo</span>
                                            {{ trans('messages.template.upload_thumbnail') }}
                                        </a>
                                    </li>
                                @endif
                                @if (Auth::user()->customer->can('update', $template))
                                    <li>
                                        <a class="dropdown-item template-categories"
                                            href="{{ action('TemplateController@categories', [
                                                'uid' => $template->uid,
                                            ]) }}">
                                            <span class="material-symbols-rounded">category</span>
                                            {{ trans('messages.template.categories') }}
                                        </a>
                                    </li>
                                @endif
                                @if (Auth::user()->customer->can('read', $template))
                                    <li>
                                        <a href="{{ action('TemplateController@export', $template->uid) }}"
                                            role="button" class="dropdown-item" link-method="POST">
                                            <span class="material-symbols-rounded me-2">download</span>
                                            {{ trans('messages.template.export') }}
                                        </a>
                                    </li>
                                @endif
                                @if (Auth::user()->customer->can('copy', $template))
                                    <li>
                                        <a href="{{ action('TemplateController@copy', $template->uid) }}"
                                            role="button" class="dropdown-item copy-template-button" link-method="GET">
                                            <span class="material-symbols-rounded me-2">copy_all</span>
                                            {{ trans('messages.template.copy') }}
                                        </a>
                                    </li>
                                @endif
                                @if (Auth::user()->customer->can('delete', $template))
                                    <li><a class="dropdown-item list-action-single"
                                            link-confirm="{{ trans('messages.delete_templates_confirm') }}"
                                            href="{{ action('TemplateController@delete', ['uids' => $template->uid]) }}">
                                            <span class="material-symbols-rounded me-2">delete_outline</span>
                                            {{ trans('messages.delete') }}</a></li>
                                @endif
                            </ul>
                        </div>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
    @include('elements/_per_page_select', ['items' => $templates])

    <script>
        $(function() {
            // change name click
            $('.change-template-name').on('click', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');

                TemplatesList.getChangeNamePopup().load({
                    url: url
                });
            });

            $('.copy-template-button').on('click', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');

                TemplatesList.getCopyPopup().load({
                    url: url
                });
            });

            $('.template-compose').click(function(e) {
                e.preventDefault();

                var url = $(this).attr('href');

                openBuilder(url);
            });

            $('.template-compose-classic').click(function(e) {
                e.preventDefault();

                var url = $(this).attr('href');

                openBuilderClassic(url);
            });
        });


        var TemplatesList = {
            copyPopup: null,
            changeNamePopup: null,

            getCopyPopup: function() {
                if (this.copyPopup === null) {
                    this.copyPopup = new Popup();
                }

                return this.copyPopup;
            },

            getChangeNamePopup: function() {
                if (this.changeNamePopup === null) {
                    this.changeNamePopup = new Popup();
                }

                return this.changeNamePopup;
            }
        }
    </script>

    <script>
        var thumbPopup = new Popup();
        var categoriesPopup = new Popup();

        $('.upload-thumb-button').click(function(e) {
            e.preventDefault();

            var url = $(this).attr('href');

            thumbPopup.load(url);
        });

        $('.template-categories').click(function(e) {
            e.preventDefault();

            var url = $(this).attr('href');

            categoriesPopup.load(url);
        });
    </script>
@elseif (!empty(request()->keyword))
    <div class="empty-list">
        <span class="material-symbols-rounded">auto_awesome_mosaic</span>
        <span class="line-1">
            {{ trans('messages.no_search_result') }}
        </span>
    </div>
@else
    <div class="empty-list">
        <span class="material-symbols-rounded">auto_awesome_mosaic</span>
        <span class="line-1">
            {{ trans('messages.template_empty_line_1') }}
        </span>
    </div>
@endif
