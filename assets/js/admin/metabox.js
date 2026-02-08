const { __ } = wp.i18n;
const { PluginDocumentSettingPanel } = wp.editPost;
const { CheckboxControl } = wp.components;
const { dispatch, useSelect } = wp.data;
const { registerPlugin } = wp.plugins;

/**
 * SwiftPressMetaBox
 *
 * @returns PluginDocumentSettingPanel
 */
const SwiftPressMetaBox = () => {
	const meta = useSelect((select) => select('core/editor').getEditedPostAttribute('meta'));

	if (!meta) {
		return null;
	}

	if (
		!('swiftpress_disable_cache' in meta) &&
		!('swiftpress_disable_lazyload' in meta) &&
		!('swiftpress_disable_css_optimization' in meta) &&
		!('swiftpress_disable_js_optimization' in meta) &&
		!('swiftpress_disable_js_defer' in meta) &&
		!('swiftpress_disable_js_delay' in meta) &&
		!('swiftpress_disable_critical_css' in meta) &&
		!('swiftpress_specific_critical_css' in meta) &&
		!('swiftpress_disable_ucss' in meta) &&
		!('swiftpress_specific_ucss' in meta)
	) {
		return null; // nothing to control
	}

	const disableCache = meta.swiftpress_disable_cache || false;
	const disableLazyLoad = meta.swiftpress_disable_lazyload || false;
	const disableCSSOptimization = meta.swiftpress_disable_css_optimization || false;
	const disableJSOptimization = meta.swiftpress_disable_js_optimization || false;
	const disableJSDefer = meta.swiftpress_disable_js_defer || false;
	const disableJSDelay = meta.swiftpress_disable_js_delay || false;
	const disableCritical = meta.swiftpress_disable_critical_css || false;
	const specificCritical = meta.swiftpress_specific_critical_css || false;
	const disableUCSS = meta.swiftpress_disable_ucss || false;
	const specificUCSS = meta.swiftpress_specific_ucss || false;

	return (
		<PluginDocumentSettingPanel
			icon="superhero"
			title={__('SwiftPress', 'swiftpress')}
			className="swiftpress-panel"
			name="cache-panel"
		>
			{'swiftpress_disable_cache' in meta && (
				<CheckboxControl
					label={__("Don't cache this post", 'swiftpress')}
					checked={disableCache}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: { swiftpress_disable_cache: !disableCache },
						});
					}}
				/>
			)}

			{'swiftpress_disable_lazyload' in meta && (
				<CheckboxControl
					label={__('Disable lazy loading for this post', 'swiftpress')}
					checked={disableLazyLoad}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: { swiftpress_disable_lazyload: !disableLazyLoad },
						});
					}}
				/>
			)}

			{'swiftpress_disable_css_optimization' in meta && (
				<CheckboxControl
					label={__('Disable CSS optimization', 'swiftpress')}
					checked={disableCSSOptimization}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: {
								swiftpress_disable_css_optimization: !disableCSSOptimization,
							},
						});
					}}
				/>
			)}

			{'swiftpress_disable_js_optimization' in meta && (
				<CheckboxControl
					label={__('Disable JS optimization', 'swiftpress')}
					checked={disableJSOptimization}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: { swiftpress_disable_js_optimization: !disableJSOptimization },
						});
					}}
				/>
			)}

			{'swiftpress_disable_js_defer' in meta && (
				<CheckboxControl
					label={__('Disable JS defer for this post', 'swiftpress')}
					checked={disableJSDefer}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: { swiftpress_disable_js_defer: !disableJSDefer },
						});
					}}
				/>
			)}

			{'swiftpress_disable_js_delay' in meta && (
				<CheckboxControl
					label={__('Disable JS delay for this post', 'swiftpress')}
					checked={disableJSDelay}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: { swiftpress_disable_js_delay: !disableJSDelay },
						});
					}}
				/>
			)}

			{'swiftpress_disable_critical_css' in meta && !specificCritical && (
				<CheckboxControl
					label={__('Disable Critical CSS for this post', 'swiftpress')}
					checked={disableCritical}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: {
								swiftpress_disable_critical_css: !disableCritical,
							},
						});
					}}
				/>
			)}

			{'swiftpress_specific_critical_css' in meta && !disableCritical && (
				<CheckboxControl
					label={__('Generate specific Critical CSS', 'swiftpress')}
					checked={specificCritical}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: {
								swiftpress_specific_critical_css: !specificCritical,
							},
						});
					}}
				/>
			)}

			{'swiftpress_disable_ucss' in meta && !specificUCSS && (
				<CheckboxControl
					label={__('Disable UCSS for this post', 'swiftpress')}
					checked={disableUCSS}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: {
								swiftpress_disable_ucss: !disableUCSS,
							},
						});
					}}
				/>
			)}

			{'swiftpress_specific_ucss' in meta && !disableUCSS && (
				<CheckboxControl
					label={__('Generate specific UCSS', 'swiftpress')}
					checked={specificUCSS}
					onChange={() => {
						dispatch('core/editor').editPost({
							meta: {
								swiftpress_specific_ucss: !specificUCSS,
							},
						});
					}}
				/>
			)}
		</PluginDocumentSettingPanel>
	);
};

registerPlugin('swiftpress-post-meta', { render: SwiftPressMetaBox });
