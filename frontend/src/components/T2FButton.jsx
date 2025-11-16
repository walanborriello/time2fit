import React from 'react';
import './T2FButton.css';

const T2FButton = ({ children, onClick, variant = 'primary', size = 'medium', ...props }) => {
  const className = `t2f-button t2f-button--${variant} t2f-button--${size}`;
  
  return (
    <button className={className} onClick={onClick} {...props}>
      {children}
    </button>
  );
};

export default T2FButton;

