import React from 'react';
import './T2FInput.css';

const T2FInput = ({ label, error, ...props }) => {
  return (
    <div className="t2f-input-wrapper">
      {label && <label className="t2f-input-label">{label}</label>}
      <input className={`t2f-input ${error ? 't2f-input--error' : ''}`} {...props} />
      {error && <span className="t2f-input-error">{error}</span>}
    </div>
  );
};

export default T2FInput;

