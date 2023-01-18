jQuery(document).ready(function(){

	// Set "refresh" link handler
	jQuery(".ct_widget_refresh_link").on('click', function(){
		jQuery('.ct_preloader').show();
		setTimeout(function(){window.scrollTo(0, jQuery('#ct_widget_wrapper').offset().top - 130);}, 1);
		setTimeout(function(){jQuery("#ct_refresh_form").submit();}, 2500);
	});

	if(location.hash == '#ct_widget')
		setTimeout(function(){window.scrollTo(0, jQuery('#ct_widget_wrapper').offset().top - 130);}, 1);

	// Fixing default wrapper style
	jQuery("#ct_widget_wrapper").parent().css('padding', 0);

	locale = navigator.language || navigator.userLanguage;

	var date_formatter = new Intl.DateTimeFormat(locale, {
		month: "short",
		day: "numeric"
	});

	function reformatWidgetData(apbctDashboardWidget){
		let widgetData = {'labels':[],'counts':[]}
		for (let i = 0; i < apbctDashboardWidget.length; i++) {
			widgetData.labels.push(date_formatter.format(new Date(apbctDashboardWidget[i]['0'])))
			widgetData.counts.push(apbctDashboardWidget[i]['1'])
		}
		return widgetData
	}

	const ctx = document.getElementById('ct_widget_chart')
	Chart.defaults.plugins.legend.display = false
	widgetData = reformatWidgetData(apbctDashboardWidget['data'])

	new Chart(ctx, {
		type: 'bar',
		data: {
			labels: widgetData.labels,
			datasets: [{
				label: 'Spam blocked',
				data: widgetData.counts,
				borderWidth: 1
			}]
		},
		options: {
			maintainAspectRatio: false,
			responsive: true,
			scales: {
				y: {
					beginAtZero: true
				}
			},
			plugins: {
				title: {
					display: true,
					text: 'Spam attacks',
					font: {
						size: 18,
					}
				},
			},
			elements: {
				bar:{
					backgroundColor: 'steelblue'
				}
			},
			animations: {
				tension: {
					duration: 1000,
					easing: 'linear',
					from: 1,
					to: 0,
					loop: true
				}
			},
		}
	});

});