/**
 * Mounts the @wordpress/dataviews table for the "Statistics & History" tab.
 * callback for how dist/js/darkup-history.js + its .asset.php get enqueued.
 */
import { createRoot, useState, useEffect } from '@wordpress/element';
import { DataViews } from '@wordpress/dataviews';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
// DataViews' stylesheet is copied to dist/js/darkup-history.css by webpack.config.js's
// CopyWebpackPlugin and enqueued directly in admin.php — not imported here, since
// @wordpress/dataviews declares "sideEffects": false, which lets production tree-shaking
// silently drop a bare `import '...css'` (nothing ever references its non-existent exports).

const defaultView = {
	type: 'table',
	page: 1,
	perPage: 20,
	search: '',
	filters: [],
	sort: { field: 'date', direction: 'desc' },
	fields: ['date', 'message', 'gallery', 'user', 'message_type'],
};

const fields = [
	{
		id: 'message',
		label: __('message', 'darkup'),
		enableHiding: false,
		enableSorting: false,
		/*render: ( { item } ) => (
			<div style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
				{ item.thumbnail && (
					<img
						src={ item.thumbnail }
						alt=""
						width={ 40 }
						height={ 40 }
						style={ { objectFit: 'cover' } }
					/>
				) }
				<span>{ item.label }</span>
			</div>
		),*/
	},
	{ id: 'message_type', label: __('Message type', 'darkup'), enableSorting: true },
	{ id: 'gallery', label: __('Gallery', 'darkup'), enableSorting: true },
	{ id: 'user', label: __('User', 'darkup'), enableSorting: false },
	{ id: 'date', label: __('Date', 'darkup') },
];

function HistoryApp() {
	const [view, setView] = useState(defaultView);
	const [data, setData] = useState({ items: [], total: 0, totalPages: 0, isLoading: true });

	useEffect(() => {
		setData((prev) => ({ ...prev, isLoading: true }));

		const query = { page: view.page, per_page: view.perPage };
		if (view.search) {
			query.search = view.search;
		}
		if (view.sort?.field) {
			query.orderby = view.sort.field;
			query.order = view.sort.direction;
		}

		apiFetch({ path: addQueryArgs('/darkup/v1/logs', query) })
			.then((response) => {
				setData({
					items: response.items || [],
					total: response.total || 0,
					totalPages: response.total_pages || 0,
					isLoading: false,
				});
			})
			.catch(() => {
				setData({ items: [], total: 0, totalPages: 0, isLoading: false });
			});
		// view.sort is a new object on every onChangeView call, so depend on its
		// primitive fields — depending on the object itself would either miss
		// updates or re-fetch on unrelated renders depending on reference identity.
	}, [view.page, view.perPage, view.search, view.sort?.field, view.sort?.direction]);

	return (
		<DataViews
			data={data.items}
			fields={fields}
			view={view}
			onChangeView={setView}
			paginationInfo={{ totalItems: data.total, totalPages: data.totalPages }}
			isLoading={data.isLoading}
			defaultLayouts={{ table: {} }}
			getItemId={(item) => String(item.id)}
		/>
	);
}

const container = document.getElementById('darkup-history-root');
if (container) {
	createRoot(container).render(<HistoryApp />);
}
