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
	
	// Chart 
	var ct_chart = jQuery("#ct_widget_chart"),
		locale = navigator.language || navigator.userLanguage;
		
	function ctParseData(date){
		var date_formatter = new Intl.DateTimeFormat(locale, {
			month: "short",
			day: "numeric"
		});		
		date.forEach(function(d){	
			d[0] = Date.parse(d[0]);
			d[0] = date_formatter.format(d[0]);
		});		
	}
		
	google.charts.load('current', {packages:['corechart', 'bar']});
	google.charts.setOnLoadCallback(drawStuff);

	function drawStuff() {
		var data = new google.visualization.DataTable();
		data.addColumn('string', 'Spam Blocked');
		data.addColumn('number', 'Frequency');
		
		ctParseData(ct_chart_data);
		data.addRows(ct_chart_data);
	
		var options = {
			width: jQuery(".ct_widget_block").first().width(),
			height: 300,
			colors: ['steelblue'],
			legend: 'none',
			bar: {groupWidth: '95%'},
			chartArea:{left:30,top:20,width:'93%',height:'80%'},
			vAxis: { gridlines: { count: 5 } }
		};

		var chart = new google.visualization.ColumnChart(document.getElementById('ct_widget_chart'));
		chart.draw(data, options);
	};	
});