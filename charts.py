from graphos.sources.simple import SimpleDataSource
from graphos.renderers import morris,gchart
from views import *

def morris_chart(chart_data,title):
	chart_data2=[['Name',title]] + chart_data
	#chart_data2= [['Name','Sale Volume share']] + [['Grocery',56.97],['Personal Care',17.85],['Medicine',18.51],['Magazines',6.68]]
	return morris.DonutChart(SimpleDataSource(chart_data2), options={'title':title})

def gchart(chart_data,title):
	chart_data2 = [['Name',title]] + chart_data
	#return [['Name','Sale Volume share']] + [['Grocery',56.97],['Personal Care',17.85],['Medicine',18.51],['Magazines',6.68]]    
	return chart_data2

def chartjs(chart_data,title):
    chart_data2 = []
    for elem in chart_data:
        dic = dict(label=elem[0],value=elem[1])
        chart_data2.append(dic)
    return chart_data2