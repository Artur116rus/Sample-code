package com.example.atlasova.ksuz;
import android.app.ProgressDialog;
import android.os.Bundle;
import android.support.v4.app.Fragment;
import android.support.v4.app.FragmentTransaction;
import android.support.v7.app.ActionBar;
import android.support.v7.app.AppCompatActivity;
import android.support.v7.widget.LinearLayoutManager;
import android.support.v7.widget.RecyclerView;
import android.text.Html;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.GridView;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import com.example.atlasova.ksuz.helper.DatabaseHelper;
import com.example.atlasova.ksuz.model.TaskFileModel;
import com.example.atlasova.ksuz.model.User;
import com.google.gson.Gson;
import com.google.gson.GsonBuilder;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

import io.realm.Realm;
import io.realm.RealmQuery;
import io.realm.RealmResults;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;


/**
 * A simple {@link Fragment} subclass.
 */
public class ViewTaskFragment extends Fragment{

    private ApiService api = RetroClient.getApiService();
    private Gson gson = new GsonBuilder().create();
    FragmentTransaction fTrans;
    private JSONObject jsonObject;
    public Bundle tempBundle;
    int user_id;
    int priority;
    int status;
    int who_insert_id;
    private Realm mRealm;
    LinearLayout btnReady;
    LinearLayout btnClose;
    LinearLayout btnReturn;

    LinearLayout readyTextLiner;
    LinearLayout closeTextLiner;
    LinearLayout closeDirectorTextLiner;

    TextView statusText;

    TaskFileModel modelFile;

    MyRecyclerViewFilesAdapter adapter;

    private GridView gridView;
    ProgressDialog pd;


    public ViewTaskFragment() {
        // Required empty public constructor
    }


    public static ViewTaskFragment newInstance() {
        ViewTaskFragment fragment = new ViewTaskFragment();
        return fragment;
    }

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        mRealm.init(getActivity());
        mRealm = new DatabaseHelper().getInstance();
        RealmQuery<User> query = mRealm.where(User.class);
        RealmResults<User> result1 = query.findAll();
        user_id = (int) result1.get(0).getId();
    }

    @Override
    public View onCreateView(LayoutInflater inflater, ViewGroup container, Bundle savedInstanceState) {

        System.out.println("Нахожусь в просмотре задания");
        getActivity().setTitle("Просмотр задания");
        ActionBar actionBar = ((AppCompatActivity)getActivity()).getSupportActionBar();
        actionBar.setHomeButtonEnabled(true);
        actionBar.setDisplayHomeAsUpEnabled(true);

        Log.e("Task_id - ", String.valueOf(this.getArguments().getInt("task_id")));

        final int getTaskId = this.getArguments().getInt("task_id");
        System.out.println("Получили task_id - "+getTaskId);

        final View v = inflater.inflate(R.layout.fragment_view_task, container, false);

        btnReady = (LinearLayout) v.findViewById(R.id.linerReadyTask);
        btnClose = (LinearLayout) v.findViewById(R.id.linerCloseTask);
        btnReturn = (LinearLayout) v.findViewById(R.id.linerReturnTask);

        readyTextLiner = (LinearLayout) v.findViewById(R.id.readyTextLiner);
        closeTextLiner = (LinearLayout) v.findViewById(R.id.closeTextLiner);
        closeDirectorTextLiner = (LinearLayout) v.findViewById(R.id.closeDirectorTextLiner);

        Map<String, Integer> mapJson = new HashMap<String, Integer>();
        mapJson.put("task_id", getTaskId);
        pd = new ProgressDialog(getActivity());
        pd.setMessage("Идет загрузка...");
        pd.show();
        RetroClient.getApiService().viewTask(mapJson).enqueue(new Callback<Object>() {
            @Override
            public void onResponse(Call<Object> call, Response<Object> response) {
                System.out.println("Response - "+response);
                if (response.isSuccessful() && response.body() != null) {
                    String myJson = gson.toJson(response.body());
                    System.out.println("А вот он - "+myJson);
                    Map<String, String> map = gson.fromJson(myJson.toString(),Map.class);
                    System.out.println("Ну что "+map);
                    if(map.get("status").equals("success")){
                        try {
                            JSONObject dataJsonObj = new JSONObject(map);
                            JSONArray resultJson = dataJsonObj.getJSONArray("review");
                            JSONObject result = resultJson.getJSONObject(0);
                            int task_id = result.getInt("multitask_id");
                            String full = result.getString("multitask_full");
                            String dateCreate = result.getString("multitask_date_my");

                            String my_null_Date = "00.00.0000 00:00";

                            status = result.getInt("multitask_status");
                            statusText = (TextView) v.findViewById(R.id.status);


                            priority = result.getInt("multitask_priority");
                            System.out.println(priority);
                            TextView priorityText = (TextView) v.findViewById(R.id.priority);
                            if(priority == 1){
                                priorityText.setText(R.string.low_priority);
                            } else if(priority == 2){
                                priorityText.setText(R.string.average_priority);
                            } else if(priority == 3){
                                priorityText.setText(R.string.high_priority);
                            } else if(priority == 4){
                                priorityText.setText(R.string.ev_priority);
                            }

                            String date_create = result.getString("multitask_date_create_full");
                            if(date_create.isEmpty()){
                                Toast.makeText(getActivity(), "Не хватает данных с api. Обратитесь к Жене!!!", Toast.LENGTH_LONG).show();
                            } else {
                                TextView dateCText = (TextView) v.findViewById(R.id.dateCreate);
                                if(date_create.compareTo(my_null_Date) != 0){
                                    dateCText.setText(date_create);
                                } else {
                                    dateCText.setText(R.string.no_value);
                                }
                            }

                            String date_begin = result.getString("multitask_date_begin_full");
                            if(date_begin.isEmpty()){
                                Toast.makeText(getActivity(), "Не хватает данных с api. Обратитесь к Жене!!!", Toast.LENGTH_LONG).show();
                            } else {
                                System.out.println("Looooo - "+date_begin);
                                TextView dateNReadyText = (TextView) v.findViewById(R.id.dateNReady);
                                if(date_begin.compareTo(my_null_Date) != 0){
                                    dateNReadyText.setText(date_begin);
                                } else {
                                    dateNReadyText.setText(R.string.no_value);
                                }
                            }

                            String date_period = result.getString("multitask_date_period_full");
                            if(date_period.isEmpty()){
                                Toast.makeText(getActivity(), "Не хватает данных с api. Обратитесь к Жене!!!", Toast.LENGTH_LONG).show();
                            } else {
                                TextView datePText = (TextView) v.findViewById(R.id.datePeriod);
                                if(date_period.compareTo(my_null_Date) != 0){
                                    datePText.setText(date_period);
                                } else {
                                    datePText.setText(R.string.no_value);
                                }
                            }


                            int points_id = result.getInt("points_id");

                            if(points_id != 0){
                                LinearLayout curratorLayout = (LinearLayout) v.findViewById(R.id.blockObject);
                                curratorLayout.setVisibility(View.VISIBLE);
                                TextView currTextView = (TextView) v.findViewById(R.id.viewObject);
                                currTextView.setText(result.getString("points_street")+" "+result.getString("points_building"));
                            }

                            TextView textId = (TextView) v.findViewById(R.id.id);
                            textId.setText(String.valueOf(task_id));
                            TextView fullText = (TextView) v.findViewById(R.id.fullText);
                            fullText.setText(Html.fromHtml(full));
                            TextView dateCreateText = (TextView) v.findViewById(R.id.dateCreate);
                            dateCreateText.setText(dateCreate);

                            // Инициатор
                            JSONArray resultJsonWhoInsert = dataJsonObj.getJSONArray("who_insert");
                            JSONObject resultWhoInsert = resultJsonWhoInsert.getJSONObject(0);
                            String who_insert = resultWhoInsert.getString("employee_surname")+" "+resultWhoInsert.getString("employee_name").substring(0,1)+". "+resultWhoInsert.getString("employee_middle_name").substring(0,1)+".";
                            TextView whoInsertText = (TextView) v.findViewById(R.id.who_insert);
                            whoInsertText.setText(who_insert);
                            who_insert_id = resultWhoInsert.getInt("user_id");
                            JSONArray resultJsonCurrator = dataJsonObj.getJSONArray("currator");
                            JSONObject resultCurrator = resultJsonCurrator.getJSONObject(0);
                            if(resultCurrator.length() != 0){
                                String currator = resultCurrator.getString("employee_curator_surname")+" "+resultCurrator.getString("employee_curator_name").substring(0,1)+". "+resultCurrator.getString("employee_curator_middle_name").substring(0,1)+".";
                                LinearLayout curratorLayout = (LinearLayout) v.findViewById(R.id.blockCurrator);
                                curratorLayout.setVisibility(View.VISIBLE);
                                TextView currTextView = (TextView) v.findViewById(R.id.select_responsible);
                                currTextView.setText(currator);
                            }

                            viewButton(status);

                            List<String> allEmployee = new ArrayList<String>();
                            JSONArray resultJsonEmployee = dataJsonObj.getJSONArray("employee");
                            for(int j = 0; j < resultJsonEmployee.length(); j++){
                                JSONObject resultEmployee = resultJsonEmployee.getJSONObject(j);
                                String employee = resultEmployee.getString("employee_surname")+" "+resultEmployee.getString("employee_name")+" "+resultEmployee.getString("employee_middle_name");
                                allEmployee.add(employee);
                            }
                            TextView employeeText = (TextView) v.findViewById(R.id.employee);
                            employeeText.setText(allEmployee.toString().replaceAll("^\\[|\\]$", ""));


                            ArrayList<TaskFileModel> filesM = new ArrayList<>();
                            JSONArray resultFiles = dataJsonObj.getJSONArray("files");
                            for(int i = 0; i < resultFiles.length(); i++){
                                String stringFIle = resultFiles.getString(i);
                                int j = i+1;
                                String numberFile = "Файл - "+j;
                                System.out.println(stringFIle);
                                filesM.add(new TaskFileModel(numberFile, stringFIle));
                            }

                            RecyclerView recyclerView = v.findViewById(R.id.listView);
                            LinearLayoutManager manager = new LinearLayoutManager(getContext());
                            recyclerView.setLayoutManager(manager);
                            adapter = new MyRecyclerViewFilesAdapter(getActivity(), filesM);
                            recyclerView.setAdapter(adapter);
                            pd.hide();
                        } catch (JSONException e) {
                            Toast.makeText(getActivity(), R.string.invalid_json, Toast.LENGTH_LONG).show();
                            e.printStackTrace();
                        }
                    } else if(map.get("status").equals("error")){
                        Toast.makeText(getActivity(), map.get("value"), Toast.LENGTH_LONG).show();
                    }
                }
            }
            @Override
            public void onFailure(Call<Object> call, Throwable t) {
                System.out.println("Response error - " + t.getMessage());
            }
        });

        Button btnReadyB = (Button) v.findViewById(R.id.btnReadyTask);
        Button btnReturnB = (Button) v.findViewById(R.id.btnReturnTask);
        Button btnCloseB = (Button) v.findViewById(R.id.btnCloseTask);

        btnReadyB.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                pd = new ProgressDialog(getActivity());
                pd.setMessage("Идет загрузка...");
                pd.show();
                RetroClient.getApiService().closeTaskUser(getTaskId, user_id, 2).enqueue(new Callback<Object>() {
                    @Override
                    public void onResponse(Call<Object> call, Response<Object> response) {
                        System.out.println(response);
                        if (response.isSuccessful() && response.body() != null) {
                            try {
                                jsonObject = new JSONObject(response.body().toString());
                                System.out.println("Ну что "+jsonObject);
                                String status = jsonObject.getString("status");
                                System.out.println("Status - "+status);
                                if(status.equals("success")){
                                    viewButton(2);
                                    pd.hide();
                                }
                            } catch (JSONException e) {
                                Toast.makeText(getActivity(), R.string.invalid_json, Toast.LENGTH_LONG).show();
                                e.printStackTrace();
                            }
                        }
                    }

                    @Override
                    public void onFailure(Call<Object> call, Throwable t) {
                        System.out.println("Response error - " + t.getMessage());
                    }
                });
            }
        });

        btnReturnB.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                pd = new ProgressDialog(getActivity());
                pd.setMessage("Идет загрузка...");
                pd.show();
                RetroClient.getApiService().closeTaskUser(getTaskId, user_id, 1).enqueue(new Callback<Object>() {
                    @Override
                    public void onResponse(Call<Object> call, Response<Object> response) {
                        System.out.println(response);
                        if (response.isSuccessful() && response.body() != null) {
                            try {
                                jsonObject = new JSONObject(response.body().toString());
                                System.out.println("Ну что "+jsonObject);
                                String status = jsonObject.getString("status");
                                System.out.println("Status - "+status);
                                if(status.equals("success")){
                                    viewButton(1);
                                    pd.hide();
                                }
                            } catch (JSONException e) {
                                Toast.makeText(getActivity(), R.string.invalid_json, Toast.LENGTH_LONG).show();
                                e.printStackTrace();
                            }
                        }
                    }

                    @Override
                    public void onFailure(Call<Object> call, Throwable t) {
                        System.out.println("Response error - " + t.getMessage());
                    }
                });
            }
        });

        btnCloseB.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                pd = new ProgressDialog(getActivity());
                pd.setMessage("Идет загрузка...");
                pd.show();
                RetroClient.getApiService().closeTask(getTaskId, user_id, 3, priority).enqueue(new Callback<Object>() {
                    @Override
                    public void onResponse(Call<Object> call, Response<Object> response) {
                        System.out.println(response);
                        if (response.isSuccessful() && response.body() != null) {
                            try {
                                jsonObject = new JSONObject(response.body().toString());
                                System.out.println("Ну что "+jsonObject);
                                String status = jsonObject.getString("status");
                                System.out.println("Status - "+status);
                                if(status.equals("success")){
                                    viewButton(3);
                                    pd.hide();
                                }
                            } catch (JSONException e) {
                                Toast.makeText(getActivity(), R.string.invalid_json, Toast.LENGTH_LONG).show();
                                e.printStackTrace();
                            }
                        }
                    }

                    @Override
                    public void onFailure(Call<Object> call, Throwable t) {
                        System.out.println("Response error - " + t.getMessage());
                    }
                });
            }
        });
        return v;
    }

    public void hideElement(){
        btnReady.setVisibility(View.GONE);
        btnReturn.setVisibility(View.GONE);
        btnClose.setVisibility(View.GONE);
        readyTextLiner.setVisibility(View.GONE);
        closeTextLiner.setVisibility(View.GONE);
        closeDirectorTextLiner.setVisibility(View.GONE);
    }

    public void viewButton(int status){
        System.out.println(who_insert_id);
        if(who_insert_id == user_id){
            if(status == 1){
                hideElement();
                btnClose.setVisibility(View.VISIBLE);
                statusText.setText(R.string.activeTextstatus);
            } else if(status == 2){
                hideElement();
                statusText.setText(R.string.readyTextstatus);
                readyTextLiner.setVisibility(View.VISIBLE);
                btnReturn.setVisibility(View.VISIBLE);
                btnClose.setVisibility(View.VISIBLE);
            } else if(status == 3){
                hideElement();
                statusText.setText(R.string.closeTextstatus);
                closeTextLiner.setVisibility(View.VISIBLE);
            } else if(status == 4){
                statusText.setText(R.string.closeDirectorTextstatus);
                closeDirectorTextLiner.setVisibility(View.VISIBLE);

            }
        } else if(who_insert_id != user_id){
            if(status == 1){
                hideElement();
                statusText.setText(R.string.activeTextstatus);
                btnReady.setVisibility(View.VISIBLE);
            } else if(status == 2){
                hideElement();
                statusText.setText(R.string.readyTextstatus);
                readyTextLiner.setVisibility(View.VISIBLE);
            } else if(status == 3){
                hideElement();
                statusText.setText(R.string.closeTextstatus);
                closeTextLiner.setVisibility(View.VISIBLE);
            } else if(status == 4){
                hideElement();
                statusText.setText(R.string.closeDirectorTextstatus);
                closeDirectorTextLiner.setVisibility(View.VISIBLE);
            }
        }
    }
}
