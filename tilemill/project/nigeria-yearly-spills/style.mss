/* Nigeria Yearly Map of Oil Spills */
/* Based on nosdra_output.csv */
/* ************************** */

/* Color variable */
@red: rgb(98,9,0);

/* Layer style*/
/* Change year value to adjust */
/* *************************** */
#spills [year = 2012]{
  marker-fill:@red;
  marker-line-color:@red;
  marker-line-width: 1.5;
  marker-opacity:0.8;
  marker-line-opacity:0.8;
  marker-allow-overlap:true;
  marker-width: 5;
  [zoom >= 6][zoom <= 8]{
  [estimatedquantity > 0][estimatedquantity <= 500]{marker-width:10;}
  [estimatedquantity > 500][estimatedquantity <= 1000]{marker-width:20;}
  [estimatedquantity > 1000][estimatedquantity <= 1500]{marker-width:30;}
  [estimatedquantity > 1500][estimatedquantity <= 2000]{marker-width:40;}
  [estimatedquantity > 2000]{marker-width:50;}
  }
  [zoom = 9]{
  [estimatedquantity > 0][estimatedquantity <= 500]{marker-width:10;}
  [estimatedquantity > 500][estimatedquantity <= 1000]{marker-width:20;}
  [estimatedquantity > 1000][estimatedquantity <= 1500]{marker-width:30;}
  [estimatedquantity > 1500][estimatedquantity <= 2000]{marker-width:40;}
  [estimatedquantity > 2000]{marker-width:50;}
  }
  [zoom > 9]{
  [estimatedquantity > 0][estimatedquantity <= 500]{marker-width:10;}
  [estimatedquantity > 500][estimatedquantity <= 1000]{marker-width:20;}
  [estimatedquantity > 1000][estimatedquantity <= 1500]{marker-width:30;}
  [estimatedquantity > 1500][estimatedquantity <= 2000]{marker-width:40;}
  [estimatedquantity > 2000]{marker-width:50;}
  }
/*  text-name: [estimatedquantity];
  text-face-name: "Verdana Regular";
  text-allow-overlap:true;
  text-fill: #fff;
  text-size:10; */
}

