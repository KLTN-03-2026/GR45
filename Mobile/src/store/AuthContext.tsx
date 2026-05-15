import React, { createContext, useState, useEffect, ReactNode } from 'react';
import * as SecureStore from 'expo-secure-store';
import clientApi from '../services/client-api';

type AuthContextType = {
  user: any;
  isLoading: boolean;
  signIn: (data: any) => Promise<void>;
  signOut: () => Promise<void>;
  refreshUser: () => Promise<void>;
};

export const AuthContext = createContext<AuthContextType>({} as AuthContextType);

export const AuthProvider = ({ children }: { children: ReactNode }) => {
  const [user, setUser] = useState<any>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Tự động kiểm tra Token khi mở App
    const bootstrapAsync = async () => {
      try {
        console.log("============= ĐANG KIỂM TRA TOKEN =============");
        const token = await SecureStore.getItemAsync('userToken');
        if (token) {
          console.log("Token hiện tại:", token);
          const res = await clientApi.checkToken();
          console.log("Kết quả checkToken:", res.data);
          
          if (res.data.success) {
            setUser(res.data.data); // Gán info user từ api
            console.log("✅ Token hợp lệ, đã đăng nhập thành công!");
          } else {
            console.log("❌ Token không hợp lệ!");
            await SecureStore.deleteItemAsync('userToken');
          }
        } else {
          console.log("❌ Không tìm thấy token, cần đăng nhập lại.");
        }
      } catch (e: any) {
        console.log("Lỗi checkToken hoặc hết hạn:", e.response?.data || e.message);
        await SecureStore.deleteItemAsync('userToken');
      } finally {
        setIsLoading(false);
      }
    };
    bootstrapAsync();
  }, []);

  const signIn = async (loginData: any) => {
    try {
      console.log("============= ĐANG LOGIN =============");
      console.log("Dữ liệu gửi API:", loginData);
      
      const response = await clientApi.login(loginData);
      console.log("Kết quả Login API:", response.data);

      if (response.data.success) {
        const responseData = response.data.data;
        const token = responseData?.token || responseData?.access_token || response.data.token || response.data.access_token; 
        if (token) {
           await SecureStore.setItemAsync('userToken', token);
           setUser(responseData?.khach_hang || responseData?.user || responseData || response.data.user); 
           console.log("✅ Đã lưu Token thành công.");
        } else {
           console.log("⚠️ Backend báo thành công nhưng không trả về token trong key 'token' / 'access_token'!");
        }
      } else {
        throw new Error(response.data.message || 'Đăng nhập thất bại');
      }
    } catch (error: any) {
      console.log("❌ Lỗi SignIn bắt tại Context:", error.response?.data || error.message);
      throw error; // Quăng ra cho UI nhận Alert
    }
  };

  const signOut = async () => {
    await SecureStore.deleteItemAsync('userToken');
    setUser(null);
  };

  const refreshUser = async () => {
    try {
      const res = await clientApi.getProfile();
      if (res.data.success) {
        setUser(res.data.data);
      }
    } catch (error) {
      console.error("Lỗi refreshUser:", error);
    }
  };

  return (
    <AuthContext.Provider value={{ user, isLoading, signIn, signOut, refreshUser }}>
      {children}
    </AuthContext.Provider>
  );
};