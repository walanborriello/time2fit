import React from 'react';
import './T2FCard.css';

const T2FCard = ({ children, className = '', ...props }) => {
  return (
    <div className={`t2f-card ${className}`} {...props}>
      {children}
    </div>
  );
};

export default T2FCard;

